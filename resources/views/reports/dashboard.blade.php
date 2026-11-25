@extends($internal ? 'internal.layout' : 'layouts.app', ['title' => 'Commerce reports'])

@section('content')
<main class="purchase-page">
    <div class="purchase-heading"><h1>Commerce reports</h1></div>
    @include('auth.messages')
    <form method="GET" class="purchase-section">
        <label for="report-month">Reporting month (EAT)</label>
        <input id="report-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required>
        <button class="secondary-button">View report</button>
    </form>
    @foreach($reports as $report)
        <section class="purchase-section">
            <h2>{{ ['purchase' => 'Your purchases', 'sale' => 'Your sales', 'platform' => 'Platform sales'][$report['scope']] }}</h2>
            <p>Confirmed payments only. {{ $report['scope'] === 'purchase' ? 'Purchase totals include delivery and buyer fees.' : 'Sales totals show merchandise value before fees and exclude delivery.' }}</p>
            <h3>{{ $month->format('F Y') }} totals</h3>
            @forelse($report['periods']->where('period', $month->format('Y-m')) as $total)
                <p><strong>{{ $total->currency }} {{ number_format($total->amount_minor / 100, 2) }}</strong> across {{ $total->order_count }} paid {{ Str::plural('order', $total->order_count) }}</p>
            @empty
                <p>No confirmed payments in this month.</p>
            @endforelse
            @if($report['scope'] === 'platform')
                @foreach($report['periods']->where('period', $month->format('Y-m')) as $total)
                    <p>Buyer payments including delivery and buyer fees <strong>{{ $total->currency }} {{ number_format($total->purchase_minor / 100, 2) }}</strong></p>
                @endforeach
            @endif
            <h3>Monthly summary</h3>
            <div class="report-table">
                <table>
                    <thead><tr><th scope="col">Month</th><th scope="col">Paid orders</th><th scope="col">{{ $report['scope'] === 'platform' ? 'Merchandise sales' : 'Total' }}</th>@if($report['scope'] === 'platform')<th scope="col">Buyer payments</th>@endif</tr></thead>
                    <tbody>
                        @for($index = 0; $index < 12; $index++)
                            @php($period = $month->subMonths($index))
                            @forelse($report['periods']->where('period', $period->format('Y-m')) as $total)
                                <tr><th scope="row">{{ $period->format('M Y') }}</th><td>{{ $total->order_count }}</td><td>{{ $total->currency }} {{ number_format($total->amount_minor / 100, 2) }}</td>
                                @if($report['scope'] === 'platform')<td>{{ $total->currency }} {{ number_format($total->purchase_minor / 100, 2) }}</td>@endif</tr>
                            @empty
                                <tr><th scope="row">{{ $period->format('M Y') }}</th><td>0</td><td>0.00</td>@if($report['scope'] === 'platform')<td>0.00</td>@endif</tr>
                            @endforelse
                        @endfor
                    </tbody>
                </table>
            </div>
        </section>
        @if($report['products'] !== null)
            <section class="purchase-section">
                <h2>Product trends</h2>
                <div class="report-table">
                    <table>
                        <thead><tr><th scope="col">Product</th><th scope="col">Units in {{ $month->format('M Y') }}</th><th scope="col">Units in {{ $month->subMonth()->format('M Y') }}</th><th scope="col">Unit change</th><th scope="col">Sales in {{ $month->format('M Y') }}</th></tr></thead>
                        <tbody>
                            @forelse($report['products'] as $product)
                                <tr><th scope="row">{{ $product->name }}</th><td>{{ $product->units }}</td><td>{{ $product->prior_units }}</td><td>{{ sprintf('%+d', $product->units - $product->prior_units) }}</td><td>{{ $product->currency }} {{ number_format($product->sales_minor / 100, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="5">No products with confirmed sales in either month.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $report['products']->links() }}
            </section>
        @endif
    @endforeach
</main>
@endsection
