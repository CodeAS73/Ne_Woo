<?php

namespace Tests\Feature;

use App\Models\CheckoutQuote;
use App\Models\MerchantOrderFinance;
use App\Models\Order;
use App\Models\PaymentCollection;
use App\Models\ProductWidget;
use App\Services\KcbBuniClient;
use App\Services\OrderPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private ProductWidget $product;
    private User $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['newoo.fees.buyer_minor_units' => 3700, 'newoo.fees.merchant_basis_points' => 500, 'buni.mode' => 'simulator']);
        $this->withSession(['checkout_access' => 'buyer-secret']);
        $this->merchant = User::factory()->create();
        $this->merchant->forceFill(['account_type' => 'merchant', 'role_id' => Role::where('name', 'merchant')->sole()->id, 'is_active' => true])->save();
        $this->product = ProductWidget::create([
            'slug' => (string) Str::uuid(), 'name' => 'Canvas bag', 'description' => 'A fine bag.',
            'price_minor' => 100000, 'offer_minor' => 80000, 'offer_ends_at' => now()->addHour(), 'photos' => [], 'stock_quantity' => 100,
            'creator_hash' => hash('sha256', 'merchant-secret'), 'delivery_enabled' => true,
            'merchant_user_id' => $this->merchant->id,
            'pickup_enabled' => true, 'delivery_fee_minor' => 20000,
            'delivery_areas' => ['Nairobi', 'Kiambu'], 'delivery_estimate' => '1–3 days', 'pickup_details' => 'Shop 4, Nairobi',
        ]);
    }

    private function details(array $overrides = []): array
    {
        return array_replace([
            'quantity' => 2, 'full_name' => 'Test Buyer', 'phone' => '0712345678',
            'email' => 'buyer@example.test', 'notification_channel' => 'sms',
            'fulfillment_method' => 'delivery', 'delivery_area' => 'Nairobi',
            'town' => 'Westlands', 'address' => 'Example Building, Unit 4', 'landmark' => 'Example park',
            'instructions' => 'Call on arrival.',
        ], $overrides);
    }

    private function quote(array $overrides = []): CheckoutQuote
    {
        $this->post(route('checkout.quote', $this->product), $this->details($overrides))->assertRedirect();
        return CheckoutQuote::latest('created_at')->firstOrFail();
    }

    private function place(): Order
    {
        $quote = $this->quote();
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertRedirect();
        return Order::firstOrFail();
    }

    public function test_order_snapshots_one_combined_total_and_keeps_each_partys_fees_private(): void
    {
        $quote = $this->quote();
        $this->get(route('checkout.review', $quote))->assertOk()->assertSee('1,830.00')->assertDontSee('lead_fee')->assertDontSee('Your lead fee');
        $this->post(route('orders.place', $quote), ['consent' => 1, 'total_minor' => 1, 'lead_fee_minor' => 0])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(183000, $order->total_minor);
        $this->assertSame(3000, $order->buyer_fee_minor);
        $finance = MerchantOrderFinance::firstOrFail();
        $this->assertSame(8000, $finance->lead_fee_minor);
        $this->assertSame(172000, $finance->merchant_receivable_minor);
        $this->assertSame(183000, PaymentCollection::firstOrFail()->amount_minor);
        $this->assertStringNotContainsString('Test Buyer', DB::table('orders')->value('buyer_details'));
        $this->assertStringNotContainsString('Example Building', DB::table('orders')->value('fulfillment_details'));
        $this->get(route('orders.show', $order))->assertOk()->assertSee('1,830.00')->assertDontSee('Your lead fee')->assertDontSee('merchant_receivable_minor');
        $this->get(route('merchant.orders', $this->product))->assertNotFound();
        $buyer = app(OrderPresenter::class)->buyer($order);
        $this->assertArrayNotHasKey('lead_fee_minor', $buyer);
        $merchant = app(OrderPresenter::class)->merchant($order);
        $this->assertArrayNotHasKey('buyer_fee_minor', $merchant);
        $this->assertArrayNotHasKey('total_minor', $merchant);
        $this->withSession(['checkout_access' => 'merchant-secret', 'created_widgets' => []]);
        $this->actingAs($this->merchant, 'web');
        $this->get(route('merchant.orders', $this->product))->assertOk()->assertSee('1,720.00')->assertSee('80.00')->assertDontSee('Secure purchase fee')->assertDontSee('1,830.00');
        $this->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_order_placement_and_test_payment_are_idempotent(): void
    {
        $quote = $this->quote();
        foreach (range(1, 2) as $ignored) {
            $this->post(route('orders.place', $quote), ['consent' => 1])->assertRedirect();
        }
        $order = Order::firstOrFail();
        foreach (range(1, 2) as $ignored) {
            $this->post(route('orders.pay', $order))->assertRedirect();
            $this->post(route('orders.simulate', $order), ['outcome' => 'success'])->assertRedirect();
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_collections', 1);
        $this->assertDatabaseCount('merchant_order_finances', 1);
        $this->get(route('checkout.review', $quote))->assertRedirect(route('orders.show', $order));
        $this->assertSame('test_paid', $order->fresh()->payment_status);
        $this->assertSame('test_only_no_funds', MerchantOrderFinance::firstOrFail()->settlement_status);
        $this->assertSame('test_confirmed', PaymentCollection::firstOrFail()->status);
        Http::assertNothingSent();
    }

    public function test_expired_offer_or_changed_delivery_requires_fresh_review(): void
    {
        $quote = $this->quote();
        $this->product->update(['delivery_fee_minor' => 30000]);
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertSessionHasErrors('quote');
        $this->get(route('checkout.review', $quote))->assertSee('This review has expired');
        $this->assertDatabaseCount('orders', 0);
        $this->product->update(['delivery_fee_minor' => 20000, 'offer_ends_at' => now()->addMinute()]);
        $quote = $this->quote();
        $this->travel(2)->minutes();
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rejects_unoffered_delivery_unknown_areas_and_missing_contact(): void
    {
        $this->post(route('checkout.quote', $this->product), $this->details(['delivery_area' => 'Mombasa']))->assertSessionHasErrors('delivery_area');
        $this->post(route('checkout.quote', $this->product), $this->details(['phone' => '123', 'address' => '']))->assertSessionHasErrors(['phone', 'address']);
        $this->post(route('checkout.quote', $this->product), $this->details(['notification_channel' => 'email', 'email' => '']))->assertSessionHasErrors('email');
        $this->product->update(['delivery_enabled' => false]);
        $this->post(route('checkout.quote', $this->product), $this->details())->assertSessionHasErrors('fulfillment_method');
        $this->assertDatabaseCount('checkout_quotes', 0);
    }

    public function test_pickup_ignores_injected_delivery_data_and_uses_no_delivery_charge(): void
    {
        $quote = $this->quote(['fulfillment_method' => 'pickup', 'delivery_minor' => 100]);
        $this->assertSame(0, $quote->pricing['delivery_minor']);
        $this->assertArrayNotHasKey('address', $quote->details);
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertRedirect();
        $this->assertSame('Shop 4, Nairobi', Order::firstOrFail()->fulfillment_details['pickup_details']);
    }

    public function test_other_guests_cannot_view_quotes_orders_or_initiate_payments(): void
    {
        $order = $this->place();
        $this->withSession(['checkout_access' => 'other-buyer', 'created_widgets' => []]);
        $this->get(route('checkout.review', $order->checkout_quote_id))->assertNotFound();
        $this->post(route('orders.place', $order->checkout_quote_id), ['consent' => 1])->assertNotFound();
        $this->get(route('orders.show', $order))->assertNotFound();
        $this->post(route('orders.pay', $order))->assertNotFound();
        $this->post(route('orders.simulate', $order), ['outcome' => 'success'])->assertNotFound();
    }

    public function test_disabled_gateway_never_collects_or_marks_real_payment_as_paid(): void
    {
        $order = $this->place();
        config(['buni.mode' => 'disabled']);
        $this->post(route('orders.pay', $order))->assertSessionHas('payment_error');
        $this->post(route('orders.simulate', $order), ['outcome' => 'success'])->assertNotFound();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('not_initiated', PaymentCollection::firstOrFail()->status);
        Http::assertNothingSent();
    }

    public function test_fee_caps_are_enforced_on_server(): void
    {
        config(['newoo.fees.buyer_minor_units' => 99000, 'newoo.fees.merchant_basis_points' => 900]);
        $order = $this->place();
        $this->assertSame(3000, $order->buyer_fee_minor);
        $this->assertSame(500, MerchantOrderFinance::firstOrFail()->lead_fee_basis_points);
    }

    public function test_sandbox_oauth_uses_kcbs_documented_client_credentials_contract(): void
    {
        config(['buni.mode' => 'sandbox', 'buni.consumer_key' => 'fake-key', 'buni.consumer_secret' => 'fake-secret']);
        Http::fake(['https://uat.buni.kcbgroup.com/token' => Http::response(['access_token' => 'fake-token'])]);
        $this->assertSame('fake-token', app(KcbBuniClient::class)->accessToken());
        Http::assertSent(fn ($request) => $request->url() === 'https://uat.buni.kcbgroup.com/token' && $request['grant_type'] === 'client_credentials');
    }

    public function test_only_product_creator_can_change_delivery_and_existing_orders_keep_terms(): void
    {
        $order = $this->place();
        $settings = ['pickup_enabled' => 0, 'delivery_enabled' => 1, 'delivery_fee' => '300.50', 'delivery_areas' => 'Nairobi, Kiambu', 'delivery_estimate' => '2–4 days'];
        $this->get(route('merchant.delivery', $this->product))->assertNotFound();
        $this->put(route('merchant.delivery.update', $this->product), $settings)->assertNotFound();
        $this->withSession(['checkout_access' => 'merchant-secret']);
        $this->actingAs($this->merchant, 'web');
        $this->get(route('merchant.delivery', $this->product))->assertOk();
        $this->put(route('merchant.delivery.update', $this->product), $settings)->assertRedirect();
        $this->assertSame(30050, $this->product->fresh()->delivery_fee_minor);
        $this->assertFalse($this->product->fresh()->pickup_enabled);
        $this->assertSame(20000, $order->fresh()->delivery_minor);
        $this->assertSame('1–3 days', $order->fresh()->fulfillment_details['delivery_estimate']);
    }

    public function test_requires_consent_and_expires_old_reviews(): void
    {
        $quote = $this->quote();
        $this->post(route('orders.place', $quote), [])->assertSessionHasErrors('consent');
        $this->travel(16)->minutes();
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('orders', 0);
    }
}
