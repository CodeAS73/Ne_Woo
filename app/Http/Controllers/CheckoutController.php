<?php

namespace App\Http\Controllers;

use App\Models\CheckoutQuote;
use App\Models\MerchantOrderFinance;
use App\Models\Order;
use App\Models\PaymentCollection;
use App\Models\ProductWidget;
use App\Services\CheckoutAccess;
use App\Services\OrderPresenter;
use App\Services\OrderPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function details(Request $request, ProductWidget $product, OrderPricing $pricing)
    {
        app(\App\Services\StockInventory::class)->releaseExpired($product->id);
        $product->refresh();
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:99'], 'resume' => ['nullable', 'uuid']]);
        $form = [];
        if (! empty($data['resume'])) {
            $quote = CheckoutQuote::whereKey($data['resume'])->where('product_widget_id', $product->id)->firstOrFail();
            abort_unless(CheckoutAccess::owns($request, $quote), 404);
            $form = $quote->details;
        }
        $quantity = (int) ($data['quantity'] ?? $form['quantity'] ?? 1);
        if ($user = \Illuminate\Support\Facades\Auth::guard('web')->user()) {
            $form += ['full_name' => $user->name, 'email' => $user->email];
        }
        $method = $form['fulfillment_method'] ?? ($product->pickup_enabled ? 'pickup' : 'delivery');
        $fullPricing = $pricing->calculate($product, $quantity, $method);
        $cost = $pricing->buyer($fullPricing);
        $buyerRate = $fullPricing['buyer_fee_rate_bps'];

        return response()->view('checkout', compact('product', 'quantity', 'form', 'cost', 'buyerRate'))->header('Cache-Control', 'no-store, private');
    }

    public function quote(Request $request, ProductWidget $product, OrderPricing $pricing)
    {
        app(\App\Services\StockInventory::class)->releaseExpired($product->id);
        $product->refresh();
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?254|0)[17]\d{8}$/'],
            'email' => ['nullable', 'required_if:notification_channel,email', 'email:rfc', 'max:254'],
            'notification_channel' => ['required', Rule::in(['sms', 'email', 'whatsapp'])],
            'fulfillment_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'delivery_area' => ['exclude_unless:fulfillment_method,delivery', 'required', Rule::in($product->delivery_areas ?? [])],
            'town' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:100'],
            'address' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:300'],
            'landmark' => ['exclude_unless:fulfillment_method,delivery', 'nullable', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:300'],
        ]);
        $data['phone'] = '254'.substr(preg_replace('/\D/', '', $data['phone']), -9);
        app(\App\Services\StockInventory::class)->assertAvailable($product, (int) $data['quantity']);
        $cost = $pricing->calculate($product, (int) $data['quantity'], $data['fulfillment_method']);
        $quote = CheckoutQuote::create([
            'id' => (string) Str::uuid(), 'product_widget_id' => $product->id,
            'buyer_hash' => CheckoutAccess::hash($request), 'buyer_user_id' => \Illuminate\Support\Facades\Auth::guard('web')->id(), 'details' => $data,
            'pricing' => $cost, 'pricing_fingerprint' => $pricing->fingerprint($product, $cost, $data['fulfillment_method']),
            'expires_at' => now()->addMinutes(15),
        ]);

        return redirect()->route('checkout.review', $quote);
    }

    public function review(Request $request, CheckoutQuote $quote, OrderPricing $pricing)
    {
        abort_unless(CheckoutAccess::owns($request, $quote), 404);
        if ($order = Order::where('checkout_quote_id', $quote->id)->first()) {
            return redirect()->route('orders.show', $order);
        }
        $product = ProductWidget::findOrFail($quote->product_widget_id);
        $form = $quote->details;
        $cost = $pricing->buyer($quote->pricing);
        $changed = $this->changed($quote, $product, $pricing);

        return response()->view('checkout-review', compact('product', 'quote', 'form', 'cost', 'changed'))->header('Cache-Control', 'no-store, private');
    }

    public function place(Request $request, CheckoutQuote $quote, OrderPricing $pricing)
    {
        abort_unless(CheckoutAccess::owns($request, $quote), 404);
        $request->validate(['consent' => ['accepted']]);
        $order = DB::transaction(function () use ($quote, $pricing) {
            $quote = CheckoutQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('checkout_quote_id', $quote->id)->first()) {
                return $existing;
            }
            $product = ProductWidget::whereKey($quote->product_widget_id)->lockForUpdate()->firstOrFail();
            app(\App\Services\StockInventory::class)->assertAvailable($product, (int) $quote->details['quantity']);
            if ($this->changed($quote, $product, $pricing)) {
                throw ValidationException::withMessages(['quote' => 'The price, delivery options or review time has changed. Please review your details again before placing the order.']);
            }
            $form = $quote->details;
            $cost = $quote->pricing;
            $order = Order::create([
                'reference' => 'NW-'.Str::ulid(), 'checkout_quote_id' => $quote->id, 'product_widget_id' => $product->id,
                'buyer_hash' => $quote->buyer_hash, 'buyer_user_id' => $quote->buyer_user_id, 'product_name' => $product->name, 'quantity' => $form['quantity'],
                'merchant_user_id' => $product->merchant_user_id,
                ...$pricing->buyer($cost), 'currency' => 'KES', 'fulfillment_method' => $form['fulfillment_method'],
                'buyer_fee_policy_id'=>$cost['buyer_fee_policy_id'], 'buyer_fee_rate_bps'=>$cost['buyer_fee_rate_bps'],
                'buyer_details' => array_intersect_key($form, array_flip(['full_name', 'phone', 'email'])),
                'fulfillment_details' => [
                    'delivery_area' => $form['delivery_area'] ?? null, 'town' => $form['town'] ?? null,
                    'address' => $form['address'] ?? null, 'landmark' => $form['landmark'] ?? null,
                    'instructions' => $form['instructions'] ?? null,
                    'pickup_details' => $form['fulfillment_method'] === 'pickup' ? ($product->pickup_details ?: 'Arrange collection with the merchant.') : null,
                    'delivery_estimate' => $form['fulfillment_method'] === 'delivery' ? $product->delivery_estimate : null,
                ],
                'notification_channel' => $form['notification_channel'], 'consented_at' => now(),
            ]);
            MerchantOrderFinance::create([
                'order_id' => $order->id,
                ...array_intersect_key($cost, array_flip(['lead_fee_basis_points', 'lead_fee_minor', 'merchant_receivable_minor', 'fee_policy_version', 'fee_rule'])),
            ]);
            app(\App\Services\StockInventory::class)->reserve($product, $order);
            PaymentCollection::create([
                'order_id' => $order->id, 'idempotency_key' => (string) Str::uuid(),
                'amount_minor' => $order->total_minor, 'phone' => $form['phone'],
            ]);

            return $order;
        }, attempts: 3);

        return redirect()->route('orders.show', $order);
    }

    public function show(Request $request, Order $order, OrderPresenter $presenter)
    {
        abort_unless(CheckoutAccess::owns($request, $order), 404);
        app(\App\Services\StockInventory::class)->expireOrder($order->id);
        $order->refresh();
        $buyer = $presenter->buyer($order);
        $collection = PaymentCollection::where('order_id', $order->id)->firstOrFail();
        $payment = ['status' => $collection->status];
        $simulation = app()->environment(['local', 'testing']) && config('buni.mode') === 'simulator';

        $workflow = app(\App\Services\DeliveryWorkflow::class)->presentation($order);
        return response()->view('order', compact('buyer', 'payment', 'simulation', 'workflow', 'order'))->header('Cache-Control', 'no-store, private');
    }

    private function changed(CheckoutQuote $quote, ProductWidget $product, OrderPricing $pricing): bool
    {
        if ($quote->expires_at->isPast()) {
            return true;
        }
        try {
            app(\App\Services\StockInventory::class)->assertAvailable($product, (int) $quote->details['quantity']);
            $current = $pricing->calculate($product, (int) $quote->details['quantity'], $quote->details['fulfillment_method']);
            return ! hash_equals($quote->pricing_fingerprint, $pricing->fingerprint($product, $current, $quote->details['fulfillment_method']));
        } catch (ValidationException) {
            return true;
        }
    }
}
