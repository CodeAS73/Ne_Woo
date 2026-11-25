<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Order;
use App\Models\ProductWidget;
use App\Models\CheckoutQuote;
use App\Models\PaymentCollection;
use App\Models\MerchantOrderFinance;
use App\Models\OrderWorkflowEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;
    private User $buyer;
    private User $supervisor;
    private Order $order;
    private ProductWidget $product;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role_id' => Role::where('name', $role)->sole()->id, 'account_type' => config('rbac.roles.'.$role.'.type'), 'is_active' => true])->save();
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['buni.mode' => 'simulator']);
        $this->merchant = $this->user('merchant');
        $this->buyer = $this->user('buyer');
        $this->supervisor = $this->user('supervisor');
        $this->product = ProductWidget::create(['slug' => (string) Str::uuid(), 'merchant_user_id' => $this->merchant->id, 'name' => 'Delivery test bag', 'description' => 'Test', 'price_minor' => 100000, 'photos' => [], 'stock_quantity' => 100, 'delivery_enabled' => true, 'pickup_enabled' => true, 'delivery_areas' => ['Nairobi'], 'delivery_estimate' => '1–2 days']);
        $this->actingAs($this->buyer, 'web')->post(route('checkout.quote', $this->product), ['quantity'=>1, 'full_name'=>'Buyer', 'phone'=>'0700000000', 'fulfillment_method'=>'delivery', 'delivery_area'=>'Nairobi', 'town'=>'Demo town', 'address'=>'Demo address', 'notification_channel'=>'sms'])->assertRedirect();
        $this->post(route('orders.place', CheckoutQuote::sole()), ['consent'=>1])->assertRedirect();
        $this->order = Order::sole();
        $this->post(route('orders.pay', $this->order))->assertRedirect();
        $this->post(route('orders.simulate', $this->order), ['outcome'=>'success'])->assertRedirect();
    }

    private function publicAs(User $user): void
    {
        Auth::guard('internal')->logout();
        $this->actingAs($user, 'web');
    }

    private function staffAs(?User $user = null): void
    {
        Auth::guard('web')->logout();
        $this->actingAs($user ?? $this->supervisor, 'internal');
    }

    public function test_dispute_evidence_review_and_refund_recording_are_scoped_and_restock_once(): void
    {
        $this->publicAs($this->buyer);
        $public = route('orders.operations.submit', $this->order);
        $staff = route('internal.order-operations.submit', $this->order);
        $this->post($public, ['action' => 'dispute', 'note' => 'The product does not match its description.'])->assertSessionHasNoErrors();
        $case = \App\Models\OrderCase::sole();
        $this->assertSame('case_hold', MerchantOrderFinance::sole()->settlement_status);
        $this->publicAs($this->user('buyer'));
        $this->get(route('orders.operations', $this->order))->assertNotFound();
        $this->publicAs($this->merchant);
        $this->post($public, ['action' => 'evidence', 'case_id' => $case->id, 'note' => 'The item is available to return to stock.'])->assertSessionHasNoErrors();
        $this->post(route('orders.fulfill', $this->order), ['status' => 'preparing'])->assertSessionHasErrors('workflow');

        $this->staffAs();
        $this->get(route('internal.order-operations', $this->order))->assertOk()->assertSee('available to return to stock');
        $review = ['action' => 'decide', 'case_id' => $case->id, 'decision' => 'approve_refund', 'note' => 'Approved after checking the evidence.'];
        $this->post($staff, $review + ['refund_minor' => $this->order->total_minor + 1])->assertSessionHasErrors('operations');
        $this->post($staff, $review + ['refund_minor' => $this->order->total_minor, 'restock_quantity' => 1])->assertSessionHasNoErrors();
        $this->assertSame('refund_approved', $case->fresh()->status);

        $record = array_replace($review, ['decision' => 'record_refund', 'note' => 'Recorded the completed test refund.']);
        $this->post($staff, $record)->assertForbidden();
        $this->staffAs($this->user('system_admin'));
        $this->post($staff, $record)->assertSessionHasNoErrors();
        $this->post($staff, $record)->assertSessionHasErrors('operations');
        $this->assertSame('test_refund_recorded', $case->fresh()->status);
        $this->assertSame('refunded', $this->order->fresh()->status);
        $this->assertSame(100, $this->product->fresh()->stock_quantity);
    }

    public function test_recorded_partial_refunds_require_external_reference_and_reduce_the_remaining_limit(): void
    {
        $this->order->update(['payment_status' => 'paid']);
        PaymentCollection::sole()->update(['status' => 'confirmed', 'provider_reference' => 'COLLECTION-1']);
        $this->publicAs($this->buyer);
        $public = route('orders.operations.submit', $this->order);
        $staff = route('internal.order-operations.submit', $this->order);
        $this->post($public, ['action' => 'refund', 'note' => 'Request for a partial refund.'])->assertSessionHasNoErrors();
        $case = \App\Models\OrderCase::sole();
        $this->staffAs($this->user('system_admin'));
        $decision = ['action' => 'decide', 'case_id' => $case->id, 'note' => 'Reviewed the partial refund.'];
        $this->post($staff, $decision + ['decision' => 'approve_refund', 'refund_minor' => 1000])->assertSessionHasNoErrors();
        $this->post($staff, $decision + ['decision' => 'record_refund'])->assertSessionHasErrors('operations');
        $this->post($staff, $decision + ['decision' => 'record_refund', 'reference' => 'REFUND-1'])->assertSessionHasNoErrors();
        $this->assertSame('refund_reconciliation_required', MerchantOrderFinance::sole()->settlement_status);

        $this->publicAs($this->buyer);
        $this->post($public, ['action' => 'refund', 'note' => 'Request for the remainder.'])->assertSessionHasNoErrors();
        $next = \App\Models\OrderCase::latest('id')->firstOrFail();
        $this->staffAs();
        $this->post($staff, array_replace($decision, ['case_id' => $next->id, 'decision' => 'approve_refund',
            'refund_minor' => $this->order->total_minor - 999]))->assertSessionHasErrors('operations');
    }

    private function dispatch(): void
    {
        $this->publicAs($this->merchant);
        $this->post(route('orders.fulfill', $this->order), ['status'=>'preparing'])->assertRedirect();
        $this->post(route('orders.fulfill', $this->order), ['status'=>'dispatched', 'note'=>'Tracking DEMO123'])->assertRedirect();
    }

    private function confirm(): void
    {
        $this->publicAs($this->buyer);
        $this->post(route('orders.confirm-receipt', $this->order), ['received'=>1])->assertRedirect();
    }

    public function test_delivery_confirmation_review_and_approval_never_release_test_funds(): void
    {
        $this->dispatch();
        $this->post(route('orders.fulfill', $this->order), ['status'=>'delivered'])->assertRedirect();
        $this->assertNull($this->order->fresh()->buyer_confirmed_at);
        $this->assertSame('not_ready', MerchantOrderFinance::sole()->review_status);
        $this->confirm();
        $this->assertSame('pending', MerchantOrderFinance::sole()->review_status);
        $this->staffAs();
        $this->get(route('internal.delivery-reviews'))->assertOk()->assertSee('Delivery test bag');
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'approve', 'note'=>'Delivery checked and confirmed.'])->assertRedirect();
        $finance = MerchantOrderFinance::sole();
        $this->assertSame('approved', $finance->review_status);
        $this->assertSame('test_approved_no_funds', $finance->settlement_status);
        $this->assertSame($this->supervisor->id, $finance->reviewed_by);
        $this->get(route('internal.delivery-reviews', ['filter'=>'approved']))->assertOk()->assertSee('Delivery checked and confirmed.');
        Http::assertNothingSent();
    }

    public function test_buyer_confirmation_and_supervisor_approval_cannot_skip_steps(): void
    {
        $this->post(route('orders.confirm-receipt', $this->order), ['received'=>1])->assertSessionHasErrors('workflow');
        $this->publicAs($this->merchant);
        $this->post(route('orders.fulfill', $this->order), ['status'=>'delivered'])->assertSessionHasErrors('workflow');
        $this->staffAs();
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'approve', 'note'=>'Cannot skip confirmation.'])->assertSessionHasErrors('workflow');
        $this->assertSame('not_ready', MerchantOrderFinance::sole()->review_status);
    }

    public function test_unpaid_and_mismatched_payments_cannot_enter_fulfilment(): void
    {
        $this->publicAs($this->merchant);
        PaymentCollection::sole()->update(['amount_minor'=>1]);
        $this->post(route('orders.fulfill', $this->order), ['status'=>'preparing'])->assertSessionHasErrors('workflow');
        PaymentCollection::sole()->update(['amount_minor'=>$this->order->fresh()->total_minor]);
        $this->order->refresh()->update(['payment_status'=>'unpaid']);
        $this->post(route('orders.fulfill', $this->order), ['status'=>'preparing'])->assertSessionHasErrors('workflow');
        $this->assertSame('unstarted', $this->order->fresh()->fulfillment_status);
    }

    public function test_pickup_has_its_own_steps_and_requires_buyer_confirmation(): void
    {
        $this->order->update(['fulfillment_method'=>'pickup']);
        $this->publicAs($this->merchant);
        $this->post(route('orders.fulfill', $this->order), ['status'=>'preparing'])->assertRedirect();
        $this->post(route('orders.fulfill', $this->order), ['status'=>'dispatched'])->assertSessionHasErrors('workflow');
        $this->post(route('orders.fulfill', $this->order), ['status'=>'ready_for_pickup'])->assertRedirect();
        $this->confirm();
        $this->assertSame('buyer_confirmed', $this->order->fresh()->fulfillment_status);
    }

    public function test_delivery_issue_revokes_approval_and_requires_fresh_review(): void
    {
        $this->dispatch(); $this->confirm(); $this->staffAs();
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'approve','note'=>'Initial approved review.'])->assertRedirect();
        $this->publicAs($this->buyer);
        $this->post(route('orders.delivery-issue', $this->order), ['note'=>'The delivery arrived damaged.'])->assertRedirect();
        $this->assertSame('on_hold', MerchantOrderFinance::sole()->review_status);
        $this->assertTrue($this->order->fresh()->delivery_issue_open);
        $this->staffAs();
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'approve','note'=>'Issue still unresolved.'])->assertSessionHasErrors('workflow');
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'resolve_issue','note'=>'Replacement agreed and received.'])->assertRedirect();
        $this->assertFalse($this->order->fresh()->delivery_issue_open);
        $this->assertSame('pending', MerchantOrderFinance::sole()->review_status);
        $this->assertNull(MerchantOrderFinance::sole()->reviewed_by);
    }

    public function test_internal_hold_survives_later_buyer_confirmation_and_notes_remain_private(): void
    {
        $this->dispatch(); $this->staffAs();
        $this->post(route('internal.delivery-review', $this->order), ['decision'=>'hold','note'=>'PRIVATE_REVIEW_REASON'])->assertRedirect();
        $this->confirm();
        $this->assertSame('on_hold', MerchantOrderFinance::sole()->review_status);
        $this->get(route('orders.show', $this->order))->assertOk()->assertDontSee('PRIVATE_REVIEW_REASON')->assertDontSee('lead_fee_minor');
        $this->publicAs($this->merchant);
        $this->get(route('merchant.orders.all'))->assertOk()->assertDontSee('PRIVATE_REVIEW_REASON')->assertDontSee('Secure purchase fee');
        $this->assertStringNotContainsString('PRIVATE_REVIEW_REASON', DB::table('merchant_order_finances')->value('review_note'));
    }

    public function test_repeat_requests_do_not_duplicate_confirmation_or_approval_events(): void
    {
        $this->dispatch(); $this->confirm(); $this->confirm(); $this->staffAs();
        foreach (range(1,2) as $ignored) {
            $this->post(route('internal.delivery-review', $this->order), ['decision'=>'approve','note'=>'Checked once only.'])->assertRedirect();
        }
        $this->assertSame(1, OrderWorkflowEvent::where('action','buyer_confirmed')->count());
        $this->assertSame(1, OrderWorkflowEvent::where('action','supervisor_approved')->count());
    }

    public function test_roles_ownership_and_separation_of_duties_are_enforced(): void
    {
        $other = $this->user('merchant');
        $this->publicAs($other);
        $this->post(route('orders.fulfill',$this->order), ['status'=>'preparing'])->assertNotFound();
        $this->post(route('orders.confirm-receipt',$this->order), ['received'=>1])->assertNotFound();
        $support = $this->user('support'); $this->staffAs($support);
        $this->get(route('internal.delivery-reviews'))->assertForbidden();
        $this->post(route('internal.delivery-review',$this->order), ['decision'=>'approve','note'=>'Not allowed to review.'])->assertForbidden();
        $admin = $this->user('system_admin'); $this->staffAs($admin);
        $this->post(route('orders.fulfill',$this->order), ['status'=>'preparing'])->assertRedirect();
        $this->post(route('orders.fulfill',$this->order), ['status'=>'dispatched'])->assertRedirect();
        $this->confirm(); $this->staffAs($admin);
        $this->post(route('internal.delivery-review',$this->order), ['decision'=>'approve','note'=>'Cannot review own fulfilment.'])->assertForbidden();
    }

    public function test_real_confirmed_payment_can_be_approved_but_is_not_scheduled_or_transferred(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-24 10:15:00','Africa/Nairobi')->utc());
        $this->order->update(['payment_status'=>'paid']);
        PaymentCollection::sole()->update(['status'=>'confirmed','provider_reference'=>'VERIFIED-TEST-REFERENCE']);
        $this->dispatch(); $this->confirm(); $this->staffAs();
        $this->post(route('internal.delivery-review',$this->order), ['decision'=>'approve','note'=>'Verified receipt and payment.'])->assertRedirect();
        $this->assertSame('approved_awaiting_schedule', MerchantOrderFinance::sole()->settlement_status);
        $this->assertSame('2026-11-24 11:59',MerchantOrderFinance::sole()->release_eligible_at->timezone('Africa/Nairobi')->format('Y-m-d H:i'));
        $this->publicAs($this->buyer);
        $this->post(route('orders.delivery-issue',$this->order), ['note'=>'Check the delivery again.'])->assertRedirect();
        $this->assertNull(MerchantOrderFinance::sole()->release_eligible_at);
        $this->assertFalse(config('newoo.settlements.enabled'));
        Http::assertNothingSent();
    }

    public function test_guest_can_confirm_with_original_session_but_another_guest_cannot(): void
    {
        $this->dispatch();
        $hash = hash('sha256', 'guest-delivery-key');
        $this->order->refresh()->update(['buyer_user_id'=>null, 'buyer_hash'=>$hash]);
        Auth::guard('web')->logout();
        Auth::guard('internal')->logout();
        $this->withSession(['checkout_access'=>'another-guest']);
        $this->post(route('orders.confirm-receipt',$this->order), ['received'=>1])->assertNotFound();
        $this->withSession(['checkout_access'=>'guest-delivery-key']);
        $this->post(route('orders.confirm-receipt',$this->order), ['received'=>1])->assertRedirect();
        $this->assertNotNull($this->order->fresh()->buyer_confirmed_at);
    }

    public function test_product_transfer_does_not_transfer_historical_fulfilment_or_finances(): void
    {
        $newOwner = $this->user('merchant');
        $this->product->update(['merchant_user_id'=>$newOwner->id]);
        $this->publicAs($newOwner);
        $this->get(route('merchant.orders',$this->product))->assertOk()->assertDontSee($this->order->reference);
        $this->post(route('orders.fulfill',$this->order), ['status'=>'preparing'])->assertNotFound();
        $this->publicAs($this->merchant);
        $this->get(route('merchant.orders.all'))->assertOk()->assertSee($this->order->reference);
        $this->post(route('orders.fulfill',$this->order), ['status'=>'preparing'])->assertRedirect();
    }

    public function test_simulated_payments_cannot_fund_workflow_when_simulator_is_disabled(): void
    {
        config(['buni.mode'=>'disabled']);
        $this->publicAs($this->merchant);
        $this->post(route('orders.fulfill',$this->order), ['status'=>'preparing'])->assertSessionHasErrors('workflow');
        $this->assertSame('unstarted',$this->order->fresh()->fulfillment_status);
    }
}
