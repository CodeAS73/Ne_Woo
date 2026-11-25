<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentCollection;
use App\Models\MerchantOrderFinance;
use App\Services\CheckoutAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderPaymentController extends Controller
{
    public function start(Request $request, Order $order)
    {
        abort_unless(CheckoutAccess::owns($request, $order), 404);
        app(\App\Services\StockInventory::class)->expireOrder($order->id);
        if (! $this->simulationEnabled()) {
            app(\App\Services\StockInventory::class)->assertPayable($order->refresh());

            return back()->with('payment_error', 'KCB BUNI collection is not connected yet. Your order is saved and unpaid. No payment request was sent.');
        }
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\StockInventory::class)->assertPayable($order);
            $payment = PaymentCollection::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            // Repeated clicks reuse this one collection. Never start a second charge.
            if (! in_array($payment->status, ['not_initiated', 'test_failed'], true) || $order->payment_status !== 'unpaid') {
                return;
            }
            if ($order->stock_status === 'reserved' && $order->stock_reserved_until->lte(now())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'The stock reservation ended. Please place a new order.']);
            }
            $payment->update(['status' => 'test_pending']);
        });

        return redirect()->route('orders.show', $order)->with('payment_notice', 'Local test only. No STK prompt was sent and no money will be collected.');
    }

    public function simulate(Request $request, Order $order)
    {
        abort_unless($this->simulationEnabled(), 404);
        abort_unless(CheckoutAccess::owns($request, $order), 404);
        $data = $request->validate(['outcome' => ['required', Rule::in(['success', 'failure'])]]);
        DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            app(\App\Services\StockInventory::class)->assertPayable($order);
            $payment = PaymentCollection::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'test_pending' || $order->payment_status !== 'unpaid') {
                return;
            }
            if ($data['outcome'] === 'failure') {
                $payment->update(['status' => 'test_failed']);
                return;
            }
            $payment->update(['status' => 'test_confirmed', 'confirmed_at' => now()]);
            $order->update(['payment_status' => 'test_paid', 'status' => 'test_payment_confirmed', 'stock_status' => $order->stock_status === 'reserved' ? 'committed' : $order->stock_status]);
            // Test receipts never fund a real settlement or trigger merchant release.
            MerchantOrderFinance::where('order_id', $order->id)->update(['settlement_status' => 'test_only_no_funds']);
        });

        return redirect()->route('orders.show', $order);
    }

    private function simulationEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && config('buni.mode') === 'simulator';
    }
}
