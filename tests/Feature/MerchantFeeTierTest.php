<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ProductWidget;
use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\MerchantFeePolicy;
use App\Models\MerchantOrderFinance;
use App\Models\PaymentCollection;
use App\Services\OrderPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantFeeTierTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $merchant;
    private ProductWidget $product;

    private function user(string $role, bool $authority = false): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role_id'=>Role::where('name',$role)->sole()->id,'account_type'=>config('rbac.roles.'.$role.'.type'),'is_active'=>true,'can_manage_fee_tiers'=>$authority])->save();
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['buni.mode'=>'simulator']);
        $this->admin=$this->user('system_admin');
        $this->merchant=$this->user('merchant');
        $this->product=ProductWidget::create(['slug'=>(string)Str::uuid(),'name'=>'Tier test bag','description'=>'Bag','price_minor'=>100000,'photos'=>[],'stock_quantity'=>100,'pickup_enabled'=>true,'merchant_user_id'=>$this->merchant->id]);
    }

    private function publish(array $tiers, string $base='5'): void
    {
        $this->actingAs($this->admin,'internal')->post(route('internal.merchant-fees.publish'),['base_rate'=>$base,'tiers'=>$tiers])->assertSessionHasNoErrors()->assertRedirect();
        Auth::guard('internal')->logout();
    }

    private function place(int $quantity=1): Order
    {
        $response=$this->post(route('checkout.quote',$this->product),['quantity'=>$quantity,'full_name'=>'Guest','phone'=>'0700000000','fulfillment_method'=>'pickup','notification_channel'=>'sms']);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $quote=CheckoutQuote::findOrFail(basename($response->headers->get('Location')));
        $response=$this->post(route('orders.place',$quote),['consent'=>1]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        return Order::where('reference',basename($response->headers->get('Location')))->sole();
    }

    public function test_only_admin_and_individually_authorised_supervisor_can_manage_fees(): void
    {
        $supervisor=$this->user('supervisor');
        $this->actingAs($supervisor,'internal')->get(route('internal.merchant-fees'))->assertForbidden();
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>5])->assertForbidden();
        $this->actingAs($this->admin,'internal')->put(route('internal.users.update',$supervisor),['role'=>'supervisor','is_active'=>1,'fee_authority'=>1])->assertRedirect();
        $this->assertTrue($supervisor->fresh()->can_manage_fee_tiers);
        $this->actingAs($supervisor->fresh(),'internal')->get(route('internal.merchant-fees'))->assertOk()->assertSee('Monthly units sold');
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>5])->assertRedirect();
        $this->assertDatabaseCount('merchant_fee_policies',1);
        $this->actingAs($this->user('support',true),'internal')->get(route('internal.merchant-fees'))->assertForbidden();
    }

    public function test_rejects_over_cap_and_invalid_thresholds(): void
    {
        $this->actingAs($this->admin,'internal');
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>'5.01'])->assertSessionHasErrors('base_rate');
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>5,'tiers'=>[['basis'=>'unit_value','threshold'=>10000,'rate'=>2]]])->assertSessionHasErrors('tiers.0.threshold');
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>5,'tiers'=>[['basis'=>'monthly_units','threshold'=>'1.5','rate'=>2]]])->assertSessionHasErrors('tiers.0.threshold');
        $this->post(route('internal.merchant-fees.publish'),['base_rate'=>2,'tiers'=>[['basis'=>'monthly_value','threshold'=>50000,'rate'=>3]]])->assertSessionHasErrors('tiers.0.rate');
        $this->assertDatabaseCount('merchant_fee_policies',0);
    }

    public function test_high_value_uses_actual_unit_price_above_ten_thousand_and_chooses_lowest_rate(): void
    {
        $this->publish([['basis'=>'unit_value','threshold'=>'10000.01','rate'=>3],['basis'=>'monthly_value','threshold'=>20000,'rate'=>2]]);
        $pricing=app(OrderPricing::class);
        $this->product->update(['price_minor'=>1000000]);
        $this->assertSame(500,$pricing->calculate($this->product,1,'pickup')['lead_fee_basis_points']);
        $this->product->update(['price_minor'=>1000001]);
        $this->assertSame(300,$pricing->calculate($this->product,1,'pickup')['lead_fee_basis_points']);
        $this->assertSame(200,$pricing->calculate($this->product,2,'pickup')['lead_fee_basis_points']);
        $this->product->update(['offer_minor'=>900000]);
        $this->assertSame(500,$pricing->calculate($this->product,1,'pickup')['lead_fee_basis_points']);
    }

    public function test_both_monthly_paid_units_and_monthly_paid_value_qualify_without_counting_unpaid_orders(): void
    {
        $this->publish([['basis'=>'monthly_units','threshold'=>3,'rate'=>4],['basis'=>'monthly_value','threshold'=>2500,'rate'=>3]]);
        $paid=$this->place(2);
        $pricing=app(OrderPricing::class);
        $this->assertSame(500,$pricing->calculate($this->product->fresh(),1,'pickup')['lead_fee_basis_points']);
        $this->post(route('orders.pay',$paid))->assertRedirect();
        $this->post(route('orders.simulate',$paid),['outcome'=>'success'])->assertRedirect();
        $this->assertSame(300,$pricing->calculate($this->product->fresh(),1,'pickup')['lead_fee_basis_points']);
        $this->product->update(['price_minor'=>10000]);
        $this->assertSame(400,$pricing->calculate($this->product->fresh(),1,'pickup')['lead_fee_basis_points']);
        PaymentCollection::where('order_id',$paid->id)->update(['confirmed_at'=>now()->subMonths(2)]);
        $this->assertSame(500,$pricing->calculate($this->product->fresh(),1,'pickup')['lead_fee_basis_points']);
    }

    public function test_order_fee_snapshot_and_buyer_privacy_survive_policy_changes(): void
    {
        $this->publish([]);
        $order=$this->place();
        $finance=MerchantOrderFinance::where('order_id',$order->id)->sole();
        $this->assertSame(500,$finance->lead_fee_basis_points);
        $this->assertSame(5000,$finance->lead_fee_minor);
        $this->assertSame('policy-'.MerchantFeePolicy::sole()->id,$finance->fee_policy_version);
        $this->get(route('orders.show',$order))->assertOk()->assertDontSee('lead_fee')->assertDontSee('policy-')->assertDontSee('Merchant platform fee');
        $this->publish([], '2');
        $this->assertSame(500,$finance->fresh()->lead_fee_basis_points);
        $this->assertSame(200,app(OrderPricing::class)->calculate($this->product->fresh(),1,'pickup')['lead_fee_basis_points']);
    }
}
