@extends('layouts.app', ['title' => 'Order '.$buyer['reference']])
@section('content')
<main class="purchase-page">
    @include('partials.checkout-steps', ['step' => 3])
    <div class="purchase-heading"><span class="eyebrow">{{ $buyer['payment_status'] === 'test_paid' ? 'LOCAL TEST COMPLETE' : 'YOUR ORDER IS SAVED' }}</span><h1>{{ $buyer['payment_status'] === 'test_paid' ? 'Your test payment is confirmed.' : 'One order. One payment.' }}</h1><p>Order reference <strong>{{ $buyer['reference'] }}</strong></p></div>
    @if(session('payment_error'))<div class="notice error-notice" role="alert">{{ session('payment_error') }}</div>@endif
    @if(session('payment_notice'))<div class="notice" role="status">{{ session('payment_notice') }}</div>@endif
    @include('auth.messages')
    <div class="purchase-grid"><div>
        @include('partials.delivery-progress')
        <div class="purchase-section">
            <a class="text-link" href="{{ route('orders.operations', $order) }}">Order cancellations, refunds and disputes</a>
            @if($order->stock_status === 'reserved' && $payment['status'] !== 'test_pending')
                <p>Stock reserved until {{ $order->stock_reserved_until->timezone('Africa/Nairobi')->format('d M Y, H:i:s') }} EAT.</p>
            @endif
        </div>
        @if($workflow['funded'] && !$workflow['case_open'] && !$workflow['issue_open'] && !$workflow['buyer_confirmed'] && in_array($workflow['status'], ['dispatched','ready_for_pickup','delivered']))
        <form class="purchase-section receipt-confirmation" method="POST" action="{{ route('orders.confirm-receipt', $buyer['reference']) }}">@csrf<h2>Have you received your product?</h2><label class="consent-label"><input name="received" type="checkbox" value="1" required><span>I confirm that I have received this product. The order can proceed to supervisor review.</span></label><button class="primary-button">Confirm receipt</button></form>
        @endif
        @if($workflow['funded'] && !$workflow['case_open'] && !$workflow['issue_open'])<details class="purchase-section"><summary>Report a delivery problem</summary><form method="POST" action="{{ route('orders.delivery-issue', $buyer['reference']) }}">@csrf<div class="field"><label for="delivery-problem">What went wrong?</label><textarea id="delivery-problem" name="note" minlength="5" maxlength="500" rows="3" required></textarea><p class="input-help">Your description is shared with the merchant and internal reviewers.</p></div><button class="secondary-button">Report issue &amp; hold settlement</button></form></details>@endif
        <section class="purchase-section payment-panel"><h2>{{ $buyer['payment_status'] === 'test_paid' ? 'Test receipt' : 'Pay Ne_Woo with M-PESA' }}</h2><div class="payment-choice"><span class="mpesa-symbol">M</span><div><strong>KCB BUNI · M-PESA STK Push</strong><p>Payee: Ne_Woo</p></div></div><p class="payment-amount">KES {{ number_format($buyer['total_minor'] / 100, 2) }}</p>
            @if($buyer['payment_status'] === 'test_paid' && $order->status !== 'refunded')
                <div class="notice success-notice">✓ Test payment completed. No money was collected. This test cannot fund delivery settlement or merchant payout.</div>
            @elseif($order->status === 'cancelled')<div class="notice">This order was cancelled.</div>
            @elseif($order->status === 'refunded')<div class="notice">A full refund was recorded. See order operations for details.</div>
            @elseif($buyer['payment_status'] === 'paid')<div class="notice">Payment confirmed.</div>
            @elseif($buyer['stock_status'] === 'released')<div class="notice">This unpaid order expired and its reserved stock was released. Please place a new order if the product is still available.</div>
            @elseif($simulation)
                <div class="notice">Local test mode · No real STK prompt or payment. These controls let you test the order flow.</div>
                @if($payment['status'] === 'test_pending')
                    <p>A test payment is pending for {{ $buyer['buyer']['phone'] }}. Repeated requests use the same payment record.</p>
                    <form action="{{ route('orders.simulate', $buyer['reference']) }}" method="POST" class="test-payment-controls">@csrf<button class="primary-button" name="outcome" value="success">Simulate successful payment</button><button class="secondary-button" name="outcome" value="failure">Simulate cancelled payment</button></form>
                @else
                    @if($payment['status'] === 'test_failed')<div class="notice error-notice">The test payment was cancelled. No money was collected. You can try the same order again.</div>@endif
                    <p>Payment phone: <strong>{{ $buyer['buyer']['phone'] }}</strong></p><form method="POST" action="{{ route('orders.pay', $buyer['reference']) }}">@csrf<button class="primary-button" type="submit">Test M-PESA payment flow</button></form>
                @endif
            @else
                <div class="notice">Your order is awaiting payment. KCB BUNI is not connected yet; no money has been collected. You will be able to pay here once the connection is enabled.</div><button class="primary-button" type="button" disabled>Payment connection pending</button>
            @endif
            <p class="summary-footnote">Enter your M-PESA PIN only on a genuine prompt on your phone. Never send a separate payment to the merchant.</p>
        </section>
        <section class="purchase-section"><h2>Receiving your order</h2><p><strong>{{ $buyer['buyer']['full_name'] }}</strong><br>{{ $buyer['buyer']['phone'] }}</p>
            @if($buyer['fulfillment_method'] === 'delivery')<p>{{ $buyer['fulfillment']['address'] }}<br>{{ $buyer['fulfillment']['town'] }}, {{ $buyer['fulfillment']['delivery_area'] }}</p><span class="delivery-pill">{{ $buyer['fulfillment']['delivery_estimate'] }}</span>@else<p>{{ $buyer['fulfillment']['pickup_details'] }}</p>@endif
            <p>Preferred updates: {{ strtoupper($buyer['notification_channel']) }}. Messaging delivery will be connected separately.</p><p class="summary-footnote">Registered purchases appear in My account. Guest orders require the browser session used to place them.</p>
        </section>
    </div><aside class="buyer-summary"><div class="summary-heading"><h2>Your order</h2><span class="summary-tag">{{ match ($order->status) { 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'refunded' => 'Refund recorded', default => ucfirst(str_replace('_', ' ', $buyer['payment_status'])) } }}</span></div><h3>{{ $buyer['product_name'] }}</h3><p class="order-item-count">{{ $buyer['quantity'] }} × KES {{ number_format($buyer['unit_price_minor'] / 100, 2) }}</p><dl class="summary-costs"><div><dt>Product subtotal</dt><dd>KES {{ number_format($buyer['subtotal_minor'] / 100, 2) }}</dd></div><div><dt>Delivery</dt><dd>KES {{ number_format($buyer['delivery_minor'] / 100, 2) }}</dd></div><div><dt>Secure purchase fee</dt><dd>KES {{ number_format($buyer['buyer_fee_minor'] / 100, 2) }}</dd></div><div class="summary-total"><dt>Total</dt><dd>KES {{ number_format($buyer['total_minor'] / 100, 2) }}</dd></div></dl><div class="single-payment-note"><span>✓</span><p>Pay once to Ne_Woo. Delivery and your platform fee are included in this total.</p></div></aside></div>
</main>
@endsection
