<?php

namespace App\Services;

use App\Models\MerchantOrderFinance;
use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderWorkflowEvent;
use App\Models\PaymentCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderOperations
{
    public function __construct(private StockInventory $stock, private DeliveryWorkflow $delivery) {}

    public function cancel(Order $order, ?User $actor, string $side, string $note): void
    {
        DB::transaction(function () use ($order, $actor, $side, $note) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'cancelled') {
                return;
            }
            $payment = PaymentCollection::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $this->require($order->status === 'awaiting_payment' && $order->payment_status === 'unpaid'
                && in_array($payment->status, ['not_initiated', 'test_failed'], true),
                'Only unpaid orders without a pending payment can be cancelled directly. For a paid order, open a case.');
            if ($order->stock_status === 'reserved') {
                $this->stock->restore($order, $order->quantity, 'order_cancelled');
                $order->stock_status = 'released';
            }
            $order->status = 'cancelled';
            $order->save();
            $payment->update(['status' => 'cancelled']);
            $this->event($order, null, $actor, $side, 'order_cancelled', $note);
        }, attempts: 3);
    }

    public function open(Order $order, ?User $actor, string $side, string $kind, string $note): OrderCase
    {
        return DB::transaction(function () use ($order, $actor, $side, $kind, $note) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->require($this->delivery->funded($order), 'A confirmed payment is required to open a case.');
            $this->require(! OrderCase::where('order_id', $order->id)->whereIn('status', ['open', 'refund_approved'])->exists(),
                'An active case already exists. Add evidence to that case.');
            $case = OrderCase::create(['order_id' => $order->id, 'kind' => $kind]);
            $finance = MerchantOrderFinance::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($finance->settlement_status, ['released', 'paid_out'], true)) {
                $finance->update(['review_status' => 'on_hold', 'settlement_status' => 'case_hold',
                    'reviewed_by' => null, 'reviewed_at' => null, 'release_eligible_at' => null]);
            }
            $this->event($order, $case, $actor, $side, 'case_opened', $note);

            return $case;
        }, attempts: 3);
    }

    public function evidence(OrderCase $case, ?User $actor, string $side, string $note): void
    {
        DB::transaction(function () use ($case, $actor, $side, $note) {
            $order = Order::lockForUpdate()->findOrFail($case->order_id);
            $case = OrderCase::lockForUpdate()->findOrFail($case->id);
            $this->require($case->status === 'open', 'Evidence can be added while the case is open.');
            $this->event($order, $case, $actor, $side, 'case_evidence', $note);
        }, attempts: 3);
    }

    public function decide(OrderCase $case, User $actor, array $data): void
    {
        DB::transaction(function () use ($case, $actor, $data) {
            $order = Order::lockForUpdate()->findOrFail($case->order_id);
            $case = OrderCase::lockForUpdate()->findOrFail($case->id);
            abort_unless($actor->hasPermission('settlements.review'), 403);
            abort_if($order->buyer_user_id === $actor->id || $order->merchant_user_id === $actor->id
                || OrderWorkflowEvent::where('order_id', $order->id)->where('actor_id', $actor->id)
                    ->whereIn('actor_type', ['buyer', 'merchant'])->exists(), 403);
            $finance = MerchantOrderFinance::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $decision = $data['decision'];
            if ($decision === 'record_refund') {
                abort_unless($actor->isSystemAdmin(), 403);
                $this->require($case->status === 'refund_approved' && $this->delivery->funded($order), 'An approved refund and confirmed payment are required.');
                $test = $order->payment_status === 'test_paid';
                $reference = $test ? null : ($data['reference'] ?? null);
                $this->require($test || (filled($reference) && ! OrderCase::where('refund_reference', $reference)->exists()),
                    'Enter a unique reference for the verified external refund.');
                if ($case->restock_quantity > 0) {
                    $this->stock->restore($order, $case->restock_quantity, 'refund_restocked');
                }
                $case->update(['status' => $test ? 'test_refund_recorded' : 'refund_recorded', 'refund_reference' => $reference]);
                $refunded = $this->recorded($order)->sum('refund_minor');
                if ($refunded >= $order->total_minor) {
                    $order->update(['status' => 'refunded']);
                }
                if (! in_array($finance->settlement_status, ['released', 'paid_out'], true)) {
                    $finance->update(['settlement_status' => $refunded >= $order->total_minor ? 'refunded' : 'refund_reconciliation_required']);
                }
            } else {
                $this->require($case->status === 'open' || ($decision === 'dismiss' && $case->status === 'refund_approved'),
                    'This case has already been decided.');
                if ($decision === 'approve_refund') {
                    $this->require($this->delivery->funded($order), 'No confirmed payment remains eligible for refund.');
                    $amount = $data['refund_minor'] ?? 0;
                    $quantity = $data['restock_quantity'] ?? 0;
                    $this->require($amount > 0 && $amount <= $order->total_minor - $this->recorded($order)->sum('refund_minor'),
                        'Refund cannot exceed the remaining paid amount.');
                    $this->require($quantity === 0 || ($order->stock_status === 'committed'
                        && $quantity <= $order->quantity - $this->recorded($order)->sum('restock_quantity')),
                        'Restock only committed units that have been recovered or never dispatched.');
                    $case->update(['status' => 'refund_approved', 'refund_minor' => $amount, 'restock_quantity' => $quantity]);
                } else {
                    abort_if($case->status === 'refund_approved' && ! $actor->isSystemAdmin(), 403);
                    $case->update(['status' => 'dismissed']);
                    if ($finance->settlement_status === 'case_hold') {
                        $finance->update(['review_status' => 'on_hold', 'settlement_status' => $this->recorded($order)->exists()
                            ? 'refund_reconciliation_required' : ($order->payment_status === 'test_paid' ? 'test_only_no_funds' : 'held')]);
                    }
                }
            }
            $this->event($order, $case, $actor, 'supervisor', 'case_'.$case->status, $data['note']);
        }, attempts: 3);
    }

    private function recorded(Order $order)
    {
        return OrderCase::where('order_id', $order->id)->whereIn('status', ['refund_recorded', 'test_refund_recorded']);
    }

    private function event(Order $order, ?OrderCase $case, ?User $actor, string $side, string $action, string $note): void
    {
        OrderWorkflowEvent::create(['order_id' => $order->id, 'actor_id' => $actor?->id, 'actor_type' => $side,
            'action' => $action, 'visibility' => $case ? 'case:'.$case->id : 'shared', 'note' => $note]);
        SecurityAudit::record($action, 'order', $order->id, ['case_id' => $case?->id], $actor?->id);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operations' => $message]);
        }
    }
}
