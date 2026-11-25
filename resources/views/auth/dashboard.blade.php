@extends('layouts.app', ['title' => 'My account'])
@section('content')
<main class="purchase-page"><div class="purchase-heading"><span class="eyebrow">YOUR NE_WOO</span><h1>Hello, {{ $user->name }}.</h1><p>{{ ucfirst($user->account_type) }} account · {{ $user->email }}</p></div>@include('auth.messages')
@if(!$user->email_verified_at)
    <p><a href="{{ route('completion.form', 'verify-email') }}">Verify your email</a></p>
@endif
<nav class="purchase-section dashboard-actions" aria-label="Commerce reports">
    <a class="text-link" href="{{ route('account.reports') }}">Commerce reports</a>
    <a class="text-link" href="{{ route('account.transactions', 'purchase') }}">Purchase ledger</a>
    @if($user->account_type === 'merchant')
        <a class="text-link" href="{{ route('account.transactions', 'sale') }}">Sales ledger</a>
        <a class="text-link" href="{{ route('account.settlements') }}">Settlement ledger</a>
    @endif
</nav>
@if($user->account_type === 'merchant')<section class="purchase-section dashboard-actions"><h2>Merchant workspace</h2><a class="text-link" href="{{ route('merchant.orders.all') }}">All merchant orders &amp; fulfilment</a><span class="delivery-pill">KYC: {{ ucfirst($user->kyc?->status ?? 'not submitted') }}</span><a class="primary-button" href="{{ route($user->canCreateWidgets() ? 'merchant.create' : 'kyc.form') }}">{{ $user->canCreateWidgets() ? 'Create a widget' : 'Complete merchant verification' }}</a></section><h2 class="dashboard-section-title">Your products</h2><div class="account-products">@forelse($products as $product)<article class="purchase-section"><h3>{{ $product->name }}</h3><p>KES {{ number_format($product->currentPrice()/100,2) }}</p><a href="{{ route('products.show', $product) }}">Open product</a><a class="merchant-share-link" href="{{ route('merchant.share', $product) }}">Share product</a><a href="{{ route('merchant.orders', $product) }}">Orders &amp; your fees</a><p>{{ $product->stock_quantity }} available</p><a href="{{ route('merchant.stock', $product) }}">Manage stock</a><a href="{{ route('merchant.delivery', $product) }}">Delivery settings</a></article>@empty<p>Your widgets will appear here once you create a product.</p>@endforelse</div>{{ $products->links() }}@endif
<h2 class="dashboard-section-title">Your purchases</h2>@forelse($orders as $order)<section class="purchase-section"><h3>{{ $order->product_name }}</h3><p>{{ $order->quantity }} item(s) · KES {{ number_format($order->total_minor/100,2) }} · {{ str_replace('_',' ',$order->payment_status) }}</p><a class="text-link" href="{{ route('orders.show', $order) }}">Open order</a></section>@empty<div class="notice">Your account purchases will appear here. Guest shopping is also available.</div>@endforelse</main>
@endsection
