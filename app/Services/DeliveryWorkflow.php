<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderCase;
use App\Models\User;
use App\Models\PaymentCollection;
use App\Models\MerchantOrderFinance;
use App\Models\OrderWorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWorkflow
{
    public function funded(Order $order): bool
    {
        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return false;
        }

        $payment = PaymentCollection::where('order_id', $order->id)->first();
        if (! $payment || $payment->provider !== 'kcb_buni' || $order->currency !== 'KES' || $payment->amount_minor !== $order->total_minor || ! $payment->confirmed_at) {
            return false;
        }
        return ($order->payment_status === 'paid' && $payment->status === 'confirmed' && filled($payment->provider_reference))
            || (app()->environment(['local', 'testing']) && config('buni.mode') === 'simulator' && $order->payment_status === 'test_paid' && $payment->status === 'test_confirmed');
    }

    public function merchantOwns(User $user, Order $order): bool
    {
        return $user->isSystemAdmin() || ($user->hasPermission('orders.fulfill_own') && $order->merchant_user_id === $user->id);
    }

    public function updateMerchant(Order $order, User $actor, string $target, ?string $note): void
    {
        $this->locked($order, function ($order, $finance) use ($actor, $target, $note) {
            abort_unless($this->merchantOwns($actor, $order), 404);
            $this->require($this->funded($order), 'A confirmed payment is required before fulfilment.');
            $this->require(! $this->activeCase($order), 'Resolve the active order case before continuing fulfilment.');
            $this->require(! $order->delivery_issue_open, 'This order has an open delivery issue. Internal review is required first.');
            if ($target === $order->fulfillment_status) {
                return;
            }
            $next = ['unstarted' => 'preparing', 'preparing' => $order->fulfillment_method === 'delivery' ? 'dispatched' : 'ready_for_pickup', 'dispatched' => 'delivered', 'ready_for_pickup' => 'delivered'];
            $this->require(($next[$order->fulfillment_status] ?? null) === $target, 'This fulfilment step is not available for this order.');
            $order->update(['fulfillment_status' => $target]);
            $this->event($order, $actor, 'merchant', $target, $note);
        });
    }

    public function confirmBuyer(Order $order, ?User $actor): void
    {
        $this->locked($order, function ($order, $finance) use ($actor) {
            if ($order->buyer_confirmed_at) {
                return;
            }
            $this->require($this->funded($order), 'Delivery cannot be confirmed before payment is confirmed.');
            $this->require(! $this->activeCase($order), 'Resolve the active order case before confirming receipt.');
            $this->require(! $order->delivery_issue_open, 'Resolve the open delivery issue before confirming receipt.');
            $this->require(in_array($order->fulfillment_status, ['dispatched', 'ready_for_pickup', 'delivered'], true), 'The merchant must dispatch the product or make it ready for pickup first.');
            $order->update(['fulfillment_status' => 'buyer_confirmed', 'buyer_confirmed_at' => now(), 'status' => 'delivery_confirmed']);
            if ($finance->review_status !== 'on_hold') {
                $finance->update(['review_status' => 'pending', 'settlement_status' => $order->payment_status === 'test_paid' ? 'test_pending_review_no_funds' : 'pending_supervisor_review']);
            }
            $this->event($order, $actor, 'buyer', 'buyer_confirmed', null);
        });
    }

    public function reportIssue(Order $order, ?User $actor, string $note): void
    {
        $this->locked($order, function ($order, $finance) use ($actor, $note) {
            $this->require($this->funded($order), 'A delivery issue can be raised after payment is confirmed.');
            $this->require(! in_array($finance->settlement_status, ['released', 'paid_out'], true), 'Contact support about this completed settlement.');
            if ($order->delivery_issue_open) {
                return;
            }
            $this->require(! $this->activeCase($order), 'Use the existing order case to provide evidence.');
            $order->update(['delivery_issue_open' => true]);
            $finance->update(['review_status' => 'on_hold', 'settlement_status' => $order->payment_status === 'test_paid' ? 'test_on_hold_no_funds' : 'on_hold', 'reviewed_by' => null, 'reviewed_at' => null, 'release_eligible_at' => null]);
            $this->event($order, $actor, 'buyer', 'delivery_issue_reported', $note);
        });
    }

    public function review(Order $order, User $actor, string $decision, string $note): void
    {
        abort_unless($actor->hasPermission('settlements.review'), 403);
        $this->require(in_array($decision, ['approve', 'hold', 'resolve_issue'], true), 'Choose a supported review decision.');
        $this->locked($order, function ($order, $finance) use ($actor, $decision, $note) {
            // Reviewer cannot approve their own purchase, sale or fulfilment work.
            abort_if($order->buyer_user_id === $actor->id || $order->merchant_user_id === $actor->id || OrderWorkflowEvent::where('order_id', $order->id)->whereIn('actor_type', ['merchant', 'buyer'])->where('actor_id', $actor->id)->exists(), 403, 'A different internal reviewer must review this order.');
            $this->require($this->funded($order), 'This order has no confirmed payment to review.');
            $this->require(! in_array($finance->settlement_status, ['released', 'paid_out'], true), 'This settlement has already been released.');
            $this->require(! OrderCase::where('order_id', $order->id)->whereIn('status', ['open', 'refund_approved', 'refund_recorded', 'test_refund_recorded'])->exists(), 'This order requires case review or settlement reconciliation.');
            if ($decision === 'resolve_issue') {
                $this->require($order->delivery_issue_open, 'There is no open delivery issue.');
                $order->update(['delivery_issue_open' => false]);
                $finance->update(['review_status' => $order->buyer_confirmed_at ? 'pending' : 'not_ready', 'settlement_status' => $order->payment_status === 'test_paid' ? 'test_only_no_funds' : 'held', 'reviewed_by' => null, 'reviewed_at' => null, 'release_eligible_at' => null, 'review_note' => $note]);
                $this->event($order, $actor, 'supervisor', 'delivery_issue_resolved', $note, 'internal');
                return;
            }
            if ($decision === 'approve') {
                $this->require($order->merchant_user_id !== null, 'Assign the order to a merchant before approving settlement.');
                $this->require($order->buyer_confirmed_at !== null && ! $order->delivery_issue_open, 'Buyer-confirmed receipt and no open issue are required for approval.');
                if ($finance->review_status === 'approved') {
                    return;
                }
                $this->require(in_array($finance->review_status, ['pending', 'on_hold'], true), 'This order is not awaiting review.');
            }
            $finance->update(['review_status' => $decision === 'approve' ? 'approved' : 'on_hold', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note, 'release_eligible_at' => $decision === 'approve' && $order->payment_status === 'paid' ? app(PayoutWindow::class)->forApproval(now()) : null, 'settlement_status' => $order->payment_status === 'test_paid' ? ($decision === 'approve' ? 'test_approved_no_funds' : 'test_on_hold_no_funds') : ($decision === 'approve' ? 'approved_awaiting_schedule' : 'on_hold')]);
            $this->event($order, $actor, 'supervisor', $decision === 'approve' ? 'supervisor_approved' : 'supervisor_hold', $note, 'internal');
        });
    }

    public function presentation(Order $order): array
    {
        $finance = MerchantOrderFinance::where('order_id', $order->id)->firstOrFail();
        return [
            'status' => $order->fulfillment_status, 'buyer_confirmed' => $order->buyer_confirmed_at !== null,
            'issue_open' => $order->delivery_issue_open, 'case_open' => $this->activeCase($order), 'review_status' => $finance->review_status,
            'funded' => $this->funded($order), 'test' => $order->payment_status === 'test_paid',
            'events' => OrderWorkflowEvent::where('order_id', $order->id)->where('visibility', 'shared')->orderBy('id')->get()->map(fn ($event) => ['action' => $event->action, 'note' => $event->note, 'at' => $event->created_at->timezone('Africa/Nairobi')->format('d M Y, H:i').' EAT'])->all(),
        ];
    }

    private function activeCase(Order $order): bool
    {
        return OrderCase::where('order_id', $order->id)->whereIn('status', ['open', 'refund_approved'])->exists();
    }

    private function locked(Order $order, callable $action): void
    {
        DB::transaction(function () use ($order, $action) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $finance = MerchantOrderFinance::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $action($locked, $finance);
        }, attempts: 3);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['workflow' => $message]);
        }
    }

    private function event(Order $order, ?User $actor, string $type, string $action, ?string $note, string $visibility = 'shared'): void
    {
        OrderWorkflowEvent::create(['order_id' => $order->id, 'actor_id' => $actor?->id, 'actor_type' => $type, 'action' => $action, 'visibility' => $visibility, 'note' => $note]);
        SecurityAudit::record('order.'.$action, 'order', $order->id, [], $actor?->id);
    }
}
