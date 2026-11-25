@extends('layouts.app', ['title' => 'Review your order'])
@section('content')
<main class="purchase-page">
    @include('partials.checkout-steps', ['step' => 2])
    <div class="purchase-heading"><span class="eyebrow">ONE LAST LOOK</span><h1>Everything looking good?</h1><p>Check your details and total. Your payment goes to Ne_Woo.</p></div>
    @if($errors->any())<div class="notice error-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($changed)<div class="notice error-notice" role="alert">This review has expired or the product price or delivery options have changed. Return to your details to get a fresh total before placing your order.</div>@endif
    <div class="purchase-grid"><div>
        <section class="purchase-section"><div class="purchase-section-heading"><h2>Contact details</h2><a href="{{ route('products.checkout', [$product, 'resume' => $quote->id]) }}">Edit details</a></div><p><strong>{{ $form['full_name'] }}</strong><br>{{ $form['phone'] }}@if(!empty($form['email']))<br>{{ $form['email'] }}@endif</p><p>Preferred updates: {{ strtoupper($form['notification_channel']) }}</p></section>
        <section class="purchase-section"><h2>{{ $form['fulfillment_method'] === 'delivery' ? 'Delivery address' : 'Pickup details' }}</h2>
            @if($form['fulfillment_method'] === 'delivery')<p>{{ $form['address'] }}<br>{{ $form['town'] }}, {{ $form['delivery_area'] }}@if(!empty($form['landmark']))<br>Landmark: {{ $form['landmark'] }}@endif</p><span class="delivery-pill">{{ $product->delivery_estimate }}</span>
            @else<p>{{ $product->pickup_details ?: 'Arrange collection with the merchant.' }}</p>@endif
            @if(!empty($form['instructions']))<p>Order note: {{ $form['instructions'] }}</p>@endif
        </section>
        <section class="purchase-section"><h2>Payment</h2><div class="payment-choice"><span class="mpesa-symbol">M</span><div><strong>M-PESA through KCB BUNI</strong><p>Pay Ne_Woo once. The payment connection is being prepared.</p></div></div><p>Funds from verified payments will remain held until delivery is confirmed and supervisor review is complete. Placing an order does not release funds to the merchant.</p></section>
        <form action="{{ route('orders.place', $quote) }}" method="POST" class="place-order-form">@csrf<label class="consent-label"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>I confirm these details and agree to pay <strong>KES {{ number_format($cost['total_minor'] / 100, 2) }}</strong> to Ne_Woo for this order.</span></label><button type="submit" class="primary-button" @disabled($changed)>Place order &amp; continue to payment</button><p>No money is collected when you place this order.</p></form>
    </div>@include('partials.buyer-summary', ['quantity' => $form['quantity']])</div>
</main>
@endsection
