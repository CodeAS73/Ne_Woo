<?php

namespace Tests\Feature;

use App\Models\BuyerFeePolicy;
use App\Models\Role;
use App\Models\User;
use App\Services\BuyerFees;
use App\Services\OrderPricing;
use App\Models\ProductWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuyerSecurePurchaseFeeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, bool $authority=false): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role_id'=>Role::where('name',$role)->sole()->id,'account_type'=>config('rbac.roles.'.$role.'.type'),'is_active'=>true,'can_manage_buyer_fees'=>$authority])->save();
        return $user;
    }

    public function test_default_half_percent_minimum_maximum_and_rounding(): void
    {
        $fees=app(BuyerFees::class);
        foreach ([100=>3000,600000=>3000,600100=>3001,1000000=>5000,50000000=>250000,100000000=>250000] as $subtotal=>$expected) {
            $quote=$fees->quote($subtotal);
            $this->assertSame(50,$quote['rate']);
            $this->assertSame($expected,$quote['amount']);
        }
    }

    public function test_admin_and_specifically_approved_supervisor_can_change_rate(): void
    {
        $admin=$this->user('system_admin');
        $supervisor=$this->user('supervisor');
        $this->actingAs($supervisor,'internal')->get(route('internal.buyer-fees'))->assertForbidden();
        $this->actingAs($admin,'internal')->put(route('internal.users.update',$supervisor),['role'=>'supervisor','is_active'=>1,'buyer_fee_authority'=>1])->assertRedirect();
        $this->assertTrue($supervisor->fresh()->can_manage_buyer_fees);
        $this->assertFalse((bool)$supervisor->fresh()->can_manage_fee_tiers);
        $this->actingAs($supervisor->fresh(),'internal')->get(route('internal.buyer-fees'))->assertOk()->assertSee('0.50%');
        $this->post(route('internal.buyer-fees.publish'),['rate'=>'1','minimum_minor'=>0,'maximum_minor'=>9999999])->assertRedirect();
        $this->assertSame(100,app(BuyerFees::class)->quote(1000000)['rate']);
        $this->assertSame(10000,app(BuyerFees::class)->quote(1000000)['amount']);
        $latest=BuyerFeePolicy::latest('id')->firstOrFail();
        $this->assertSame(3000,$latest->minimum_minor);
        $this->assertSame(250000,$latest->maximum_minor);
        $this->actingAs($this->user('support',true),'internal')->post(route('internal.buyer-fees.publish'),['rate'=>'2'])->assertForbidden();
    }

    public function test_buyer_rate_changes_do_not_change_merchant_fees_or_add_delivery_to_the_fee_base(): void
    {
        config(['newoo.fees.merchant_basis_points'=>500]);
        $product=ProductWidget::create(['slug'=>(string)Str::uuid(),'name'=>'Fee test','description'=>'Test','price_minor'=>1000000,'photos'=>[],'delivery_enabled'=>true,'delivery_fee_minor'=>50000]);
        $pricing=app(OrderPricing::class)->calculate($product,1,'delivery');
        $this->assertSame(5000,$pricing['buyer_fee_minor']);
        $this->assertSame(50000,$pricing['lead_fee_minor']);
        $this->assertSame(1055000,$pricing['total_minor']);
        $this->assertSame(1000000,$pricing['merchant_receivable_minor']);
    }

    public function test_rate_validation_prevents_invalid_percentages(): void
    {
        $this->actingAs($this->user('system_admin'),'internal');
        foreach (['-1','0','100.01','0.123','invalid'] as $rate) {
            $this->post(route('internal.buyer-fees.publish'),['rate'=>$rate])->assertSessionHasErrors('rate');
        }
        $this->assertDatabaseCount('buyer_fee_policies',1);
    }

    public function test_policy_change_requires_new_review_and_preserves_placed_order_snapshot(): void
    {
        $product=ProductWidget::create(['slug'=>(string)Str::uuid(),'name'=>'Buyer fee snapshot','description'=>'Test','price_minor'=>1000000,'photos'=>[],'pickup_enabled'=>true,'stock_quantity'=>10]);
        $data=['quantity'=>1,'full_name'=>'Guest','phone'=>'0700000000','notification_channel'=>'sms','fulfillment_method'=>'pickup'];
        $response=$this->post(route('checkout.quote',$product),$data);
        $quote=\App\Models\CheckoutQuote::findOrFail(basename($response->headers->get('Location')));
        $this->post(route('orders.place',$quote),['consent'=>1])->assertRedirect();
        $order=\App\Models\Order::sole();
        $this->assertSame(5000,$order->buyer_fee_minor);
        $this->assertSame(50,$order->buyer_fee_rate_bps);
        $response=$this->post(route('checkout.quote',$product),$data);
        $pending=\App\Models\CheckoutQuote::findOrFail(basename($response->headers->get('Location')));
        $this->actingAs($this->user('system_admin'),'internal')->post(route('internal.buyer-fees.publish'),['rate'=>'2'])->assertRedirect();
        \Illuminate\Support\Facades\Auth::guard('internal')->logout();
        $this->post(route('orders.place',$pending),['consent'=>1])->assertSessionHasErrors('quote');
        $this->assertSame(5000,$order->fresh()->buyer_fee_minor);
        $this->assertSame(20000,app(BuyerFees::class)->quote(1000000)['amount']);
    }
}
