@extends('internal.layout', ['title' => 'Orders'])
@section('content')
<main class="purchase-page"><div class="purchase-heading"><span class="eyebrow">ORDER OPERATIONS</span><h1>Orders</h1><p>Payment status and order records. Live collection and payout release remain disabled.</p></div>@foreach($orders as $order)<section class="purchase-section"><h2>{{ $order->reference }}</h2><h3>{{ $order->product_name }}</h3><p>{{ $order->quantity }} item(s) · {{ str_replace('_',' ',$order->payment_status) }} · {{ ucfirst($order->fulfillment_method) }}</p>@if(auth('internal')->user()->hasPermission('settlements.review'))<a class="text-link" href="{{ route('internal.order-operations', $order) }}">Order operations</a>@endif
@if(auth('internal')->user()->hasPermission('orders.finance'))<a class="text-link" href="{{ route('internal.orders.detail', $order) }}">Open full order record</a>@endif</section>@endforeach{{ $orders->links() }}</main>
@endsection
