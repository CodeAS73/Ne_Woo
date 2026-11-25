@extends('layouts.app', ['title' => 'Product orders'])
@section('content')
<main class="purchase-page"><div class="purchase-heading"><span class="eyebrow">YOUR PRODUCT ORDERS</span><h1>{{ $productName }}</h1><p>Sales, your lead fee and your expected proceeds. Access is restricted to the merchant owner and authorized administration.</p></div>
    @forelse($rows as $row)<section class="purchase-section merchant-order-row"><h2>{{ $row['reference'] }}</h2><a class="text-link" href="{{ route('orders.operations', $row['reference']) }}">Order operations</a><h3>{{ $row['product_name'] }}</h3><p>{{ $row['quantity'] }} item(s) · {{ str_replace('_', ' ', $row['payment_status']) }} · {{ ucfirst($row['fulfillment_method']) }}</p><dl class="summary-costs"><div><dt>Product sale</dt><dd>KES {{ number_format($row['subtotal_minor'] / 100, 2) }}</dd></div><div><dt>Your delivery charge</dt><dd>KES {{ number_format($row['delivery_minor'] / 100, 2) }}</dd></div><div><dt>Your platform fee</dt><dd>KES {{ number_format($row['lead_fee_minor'] / 100, 2) }}</dd></div><div class="summary-total"><dt>Expected proceeds</dt><dd>KES {{ number_format($row['merchant_receivable_minor'] / 100, 2) }}</dd></div></dl><p>Settlement: {{ str_replace('_', ' ', $row['settlement_status']) }}. Release requires confirmed delivery and supervisor review. Test payments never release funds.</p>
@if($row['release_eligible_at'])<p>Earliest release slot: {{ $row['release_eligible_at'] }}. Transfers are not enabled yet.</p>@endif
@include('partials.delivery-progress', ['workflow'=>$row['workflow']])
@php($next = ['unstarted'=>'preparing','preparing'=>$row['fulfillment_method'] === 'delivery' ? 'dispatched' : 'ready_for_pickup','dispatched'=>'delivered','ready_for_pickup'=>'delivered'][$row['workflow']['status']] ?? null)
@if($next && $row['workflow']['funded'] && !$row['workflow']['case_open'] && !$row['workflow']['issue_open'])
<form method="POST" action="{{ route('orders.fulfill', $row['reference']) }}">@csrf<input type="hidden" name="status" value="{{ $next }}"><div class="field"><label for="tracking-{{ $loop->index }}">Update for the buyer (optional)</label><textarea id="tracking-{{ $loop->index }}" name="note" rows="2" maxlength="500" placeholder="Carrier, tracking reference or pickup instructions"></textarea></div><button class="primary-button">{{ ['preparing'=>'Start preparing','dispatched'=>'Mark dispatched','ready_for_pickup'=>'Mark ready for pickup','delivered'=>'Mark delivered / collected'][$next] }}</button></form>
@endif
</section>@empty<div class="notice">No orders yet. Share your product link to get started.</div>@endforelse
    {{ $pagination }}
</main>
@endsection
