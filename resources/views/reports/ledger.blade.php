@extends($internal ? 'internal.layout' : 'layouts.app', ['title' => $settlement ? 'Settlement ledger' : 'Transaction ledger'])

@section('content')
@php
    $money = fn ($value, $currency) => $currency.' '.number_format($value / 100, 2);
    $date = fn ($value) => $value?->timezone('Africa/Nairobi')->format('d M Y, H:i') ?? '—';
    $status = fn ($value) => ucfirst(str_replace('_', ' ', $value));
    $settlementRoute = $internal ? 'internal.settlements' : 'account.settlements';
@endphp
<main class="purchase-page">
    <div class="purchase-heading">
        <h1>{{ $settlement ? 'Settlement' : ['purchase' => 'Purchase transaction', 'sale' => 'Sales transaction', 'platform' => 'Transaction'][$scope] }} ledger</h1>
        <p>All recorded statuses, including unpaid and test orders. Times are in EAT.</p>
        <a class="text-link" href="{{ route($internal ? 'internal.dashboard' : 'account.dashboard') }}">Back to overview</a>
        @if($events !== null)
            <a class="text-link" href="{{ route($settlementRoute) }}">All settlements</a>
        @endif
    </div>
    <section class="purchase-section report-table">
        <table>
            <thead>
                <tr>
                    <th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Product</th><th scope="col">Quantity</th>
                    <th scope="col">Merchandise</th><th scope="col">Delivery</th><th scope="col">Payment status</th>
                    @if($settlement)
                        <th scope="col">Merchant fee</th><th scope="col">Merchant receivable</th><th scope="col">Review</th><th scope="col">Reviewed at</th><th scope="col">Settlement status</th><th scope="col">Eligible release</th>
                    @elseif($scope === 'sale')
                        <th scope="col">Collection status</th><th scope="col">Confirmed at</th>
                    @else
                        <th scope="col">Buyer fee</th><th scope="col">Buyer total</th><th scope="col">Collection status</th><th scope="col">Confirmed at</th>
                        @if($internal)<th scope="col">Provider reference</th>@endif
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $order)
                    <tr>
                        <th scope="row">
                            @if($settlement)
                                <a href="{{ route($settlementRoute, ['reference' => $order->reference]) }}">{{ $order->reference }}</a>
                            @elseif($scope === 'purchase')
                                <a href="{{ route('orders.show', $order) }}">{{ $order->reference }}</a>
                            @else
                                {{ $order->reference }}
                            @endif
                        </th>
                        <td>{{ $date($order->created_at) }}</td>
                        <td>{{ $order->product_name }}</td><td>{{ $order->quantity }}</td>
                        <td>{{ $money($order->subtotal_minor, $order->currency) }}</td><td>{{ $money($order->delivery_minor, $order->currency) }}</td>
                        <td>{{ $status($order->payment_status) }}</td>
                        @if($settlement)
                            <td>{{ $money($order->finance->lead_fee_minor, $order->currency) }}</td>
                            <td>{{ $money($order->finance->merchant_receivable_minor, $order->currency) }}</td>
                            <td>{{ $status($order->finance->review_status) }}</td><td>{{ $date($order->finance->reviewed_at) }}</td>
                            <td>{{ $status($order->finance->settlement_status) }}</td><td>{{ $date($order->finance->release_eligible_at) }}</td>
                        @elseif($scope === 'sale')
                            <td>{{ $status($order->payment?->status ?? 'not recorded') }}</td><td>{{ $date($order->payment?->confirmed_at) }}</td>
                        @else
                            <td>{{ $money($order->buyer_fee_minor, $order->currency) }}</td><td>{{ $money($order->total_minor, $order->currency) }}</td>
                            <td>{{ $status($order->payment?->status ?? 'not recorded') }}</td><td>{{ $date($order->payment?->confirmed_at) }}</td>
                            @if($internal)<td>{{ $order->payment?->provider_reference ?? '—' }}</td>@endif
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $settlement ? 13 : ($internal ? 12 : ($scope === 'purchase' ? 11 : 9)) }}">No ledger entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $rows->links() }}
    @if($events !== null)
        <section class="purchase-section report-table">
            <h2>Settlement history</h2>
            <table>
                <thead><tr><th scope="col">Time</th><th scope="col">Event</th></tr></thead>
                <tbody>
                    @forelse($events as $event)
                        <tr><td>{{ $date($event->created_at) }}</td><td>{{ $status($event->action) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No settlement events recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $events->links() }}
        </section>
    @endif
</main>
@endsection
