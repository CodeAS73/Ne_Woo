<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ProductWidget;
use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\PaymentCollection;
use App\Models\StockMovement;
use App\Services\StockInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockInventoryTest extends TestCase
{
    use RefreshDatabase;
    private User $merchant;
    private ProductWidget $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['buni.mode'=>'simulator']);
        $this->merchant = User::factory()->create();
        $this->merchant->forceFill(['role_id'=>Role::where('name','merchant')->sole()->id,'account_type'=>'merchant','is_active'=>true])->save();
        $this->product = ProductWidget::create(['slug'=>(string)Str::uuid(),'name'=>'Stock test bag','description'=>'Bag','price_minor'=>100000,'photos'=>[],'stock_quantity'=>10,'merchant_user_id'=>$this->merchant->id]);
    }

    private function quote(int $quantity): CheckoutQuote
    {
        $response = $this->post(route('checkout.quote',$this->product), ['quantity'=>$quantity,'full_name'=>'Guest','phone'=>'0700000000','notification_channel'=>'sms','fulfillment_method'=>'pickup']);
        $response->assertSessionHasNoErrors()->assertRedirect();
        return CheckoutQuote::findOrFail(basename($response->headers->get('Location')));
    }

    private function place(int $quantity): Order
    {
        $quote = $this->quote($quantity);
        $response = $this->post(route('orders.place',$quote),['consent'=>1]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        return Order::where('reference',basename($response->headers->get('Location')))->sole();
    }

    public function test_owner_can_amend_stock_but_other_merchants_and_guests_cannot(): void
    {
        $this->get(route('merchant.stock',$this->product))->assertNotFound();
        $other = User::factory()->create();
        $other->forceFill(['role_id'=>Role::where('name','merchant')->sole()->id,'account_type'=>'merchant','is_active'=>true])->save();
        $this->actingAs($other,'web')->put(route('merchant.stock.update',$this->product),['stock_quantity'=>8,'stock_version'=>0])->assertNotFound();
        $this->actingAs($this->merchant,'web')->get(route('merchant.stock',$this->product))->assertOk()->assertSee('Available to sell');
        $this->put(route('merchant.stock.update',$this->product),['stock_quantity'=>8,'stock_version'=>0])->assertRedirect();
        $this->assertSame(8,$this->product->fresh()->stock_quantity);
        $this->assertSame(1,StockMovement::where('action','merchant_adjustment')->count());
        $this->put(route('merchant.stock.update',$this->product),['stock_quantity'=>-1,'stock_version'=>1])->assertSessionHasErrors('stock_quantity');
        $this->put(route('merchant.stock.update',$this->product),['stock_quantity'=>'1.5','stock_version'=>1])->assertSessionHasErrors('stock_quantity');
    }

    public function test_stale_merchant_edits_cannot_overwrite_a_new_order_reservation(): void
    {
        $this->place(3);
        $this->actingAs($this->merchant,'web')->put(route('merchant.stock.update',$this->product),['stock_quantity'=>20,'stock_version'=>0])->assertSessionHasErrors('stock_quantity');
        $this->assertSame(7,$this->product->fresh()->stock_quantity);
    }

    public function test_two_quotes_for_last_unit_cannot_both_place_an_order(): void
    {
        $this->product->update(['stock_quantity'=>1]);
        $one=$this->quote(1); $two=$this->quote(1);
        $this->post(route('orders.place',$one),['consent'=>1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('orders.place',$two),['consent'=>1])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('orders',1);
        $this->assertSame(0,$this->product->fresh()->stock_quantity);
        $this->post(route('orders.place',$one),['consent'=>1])->assertRedirect();
        $this->assertSame(1,StockMovement::where('action','reserved')->count());
    }

    public function test_payment_commits_reserved_units_without_deducting_them_twice(): void
    {
        $order=$this->place(2);
        $this->assertSame('reserved',$order->stock_status);
        $this->post(route('orders.pay',$order))->assertRedirect();
        $this->post(route('orders.simulate',$order),['outcome'=>'success'])->assertRedirect();
        $this->post(route('orders.simulate',$order),['outcome'=>'success'])->assertRedirect();
        $this->assertSame('committed',$order->fresh()->stock_status);
        $this->assertSame(8,$this->product->fresh()->stock_quantity);
        $this->travel(31)->minutes();
        $this->assertSame(0,app(StockInventory::class)->releaseExpired());
    }

    public function test_expired_unpaid_reservation_returns_stock_once_and_cannot_be_paid(): void
    {
        $order=$this->place(4);
        $this->travel(31)->minutes();
        $this->artisan('newoo:expire-stock')->assertSuccessful();
        $this->assertSame(10,$this->product->fresh()->stock_quantity);
        $this->assertSame('released',$order->fresh()->stock_status);
        $this->artisan('newoo:expire-stock')->assertSuccessful();
        $this->assertSame(10,$this->product->fresh()->stock_quantity);
        $this->post(route('orders.pay',$order))->assertSessionHasErrors('payment');
        $this->assertSame('unpaid',$order->fresh()->payment_status);
        $this->get(route('orders.show',$order))->assertOk()->assertSee('This unpaid order expired');
    }

    public function test_discounted_orders_expire_at_five_minutes_and_cancellation_restores_stock_once(): void
    {
        $this->freezeSecond();
        $this->product->update(['offer_minor' => 80000, 'offer_ends_at' => now()->addHour()]);
        $flash = $this->place(2);
        $this->assertTrue($flash->stock_reserved_until->equalTo(now()->addMinutes(5)));
        $this->travel(5)->minutes();
        $this->assertSame(1, app(StockInventory::class)->releaseExpired());
        $this->assertSame(10, $this->product->fresh()->stock_quantity);

        $this->product->update(['offer_ends_at' => null]);
        $offer = $this->place(2);
        $this->assertTrue($offer->stock_reserved_until->equalTo(now()->addMinutes(5)));
        $this->get(route('orders.operations', $offer))->assertOk()->assertSee('Cancel unpaid order');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('orders.operations.submit', $offer), ['action' => 'cancel', 'note' => 'No longer needed'])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame('cancelled', $offer->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
        $this->post(route('orders.pay', $offer))->assertSessionHasErrors('payment');

        $this->product->update(['offer_ends_at' => now()->subMinute()]);
        $standard = $this->place(1);
        $this->assertTrue($standard->stock_reserved_until->equalTo(now()->addMinutes(30)));
        $this->travel(2)->minutes();
        $this->post(route('orders.pay', $standard))->assertRedirectToRoute('orders.show', $standard)->assertSessionHasNoErrors();
        $this->assertSame('test_pending', $standard->payment()->sole()->status);
        $this->post(route('orders.operations.submit', $standard), ['action' => 'cancel', 'note' => 'Still pending'])
            ->assertRedirect()->assertSessionHasErrors('operations');
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
    }

    public function test_pending_payments_keep_stock_reserved_until_result_is_known(): void
    {
        $order=$this->place(2);
        $this->post(route('orders.pay',$order))->assertRedirect();
        $this->travel(31)->minutes();
        $this->assertSame(0,app(StockInventory::class)->releaseExpired());
        $this->assertSame(8,$this->product->fresh()->stock_quantity);
        $this->post(route('orders.simulate',$order),['outcome'=>'failure'])->assertRedirect();
        $this->assertSame(1,app(StockInventory::class)->releaseExpired());
        $this->assertSame(10,$this->product->fresh()->stock_quantity);
    }

    public function test_stock_amendment_does_not_change_existing_reservations(): void
    {
        $order=$this->place(3);
        $this->actingAs($this->merchant,'web')->put(route('merchant.stock.update',$this->product),['stock_quantity'=>0,'stock_version'=>$this->product->fresh()->stock_version])->assertRedirect();
        $this->assertSame('reserved',$order->fresh()->stock_status);
        $this->travel(31)->minutes();
        app(StockInventory::class)->releaseExpired();
        $this->assertSame(3,$this->product->fresh()->stock_quantity);
        $this->get(route('products.show',$this->product))->assertOk()->assertSee('In stock');
    }

    public function test_out_of_stock_product_cannot_be_quoted(): void
    {
        $this->product->update(['stock_quantity'=>0]);
        $this->get(route('products.show',$this->product))->assertOk()->assertSee('Out of stock');
        $this->post(route('checkout.quote',$this->product),['quantity'=>1,'full_name'=>'Buyer','phone'=>'0700000000','notification_channel'=>'sms','fulfillment_method'=>'pickup'])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('checkout_quotes',0);
    }

    public function test_merchant_can_hide_count_without_exposing_it_in_public_views_or_checkout(): void
    {
        $this->actingAs($this->merchant,'web')->put(route('merchant.stock.update',$this->product), ['stock_quantity'=>137,'stock_version'=>0,'show_stock_level'=>0])->assertRedirect();
        $product=$this->product->fresh();
        $this->assertFalse($product->show_stock_level);
        $this->assertNull($product->presentation()['stock']);
        $this->assertTrue($product->presentation()['inStock']);
        Auth::guard('web')->logout();
        $this->get(route('products.show',$product))->assertOk()->assertSee('In stock')->assertDontSee('137 available')->assertDontSee('Out of stock');
        $this->get(route('products.embed',$product))->assertOk()->assertDontSee('137 available')->assertSee('In stock');
        $this->get(route('products.checkout',$product))->assertOk()->assertDontSee('137 unit(s)')->assertDontSee('max="137"',false);
        $product->update(['stock_quantity'=>1]);
        $response=$this->post(route('checkout.quote',$product), ['quantity'=>2,'full_name'=>'Guest','phone'=>'0700000000','notification_channel'=>'sms','fulfillment_method'=>'pickup']);
        $response->assertSessionHasErrors('quantity');
        $this->assertStringNotContainsString('Only 1',session('errors')->first('quantity'));
        $product->update(['stock_quantity'=>0]);
        $this->assertFalse($product->presentation()['inStock']);
        $this->get(route('products.show',$product))->assertOk()->assertSee('Out of stock');
    }
}
