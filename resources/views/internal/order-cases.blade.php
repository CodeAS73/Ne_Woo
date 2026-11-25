@extends('internal.layout', ['title' => 'Order cases'])

@section('content')
<main class="purchase-page">
    <div class="purchase-heading"><h1>Order cases</h1></div>
    @forelse($cases as $case)
        <article class="purchase-section">
            <h2><a class="text-link" href="{{ route('internal.order-operations', [$case->order, 'case' => $case->id]) }}">{{ $case->order->reference }} · Case {{ $case->id }}</a></h2>
            <p>{{ ucfirst($case->kind) }} · {{ ucfirst(str_replace('_', ' ', $case->status)) }}</p>
        </article>
    @empty
        <p>No order cases have been opened.</p>
    @endforelse
    {{ $cases->links() }}
</main>
@endsection
