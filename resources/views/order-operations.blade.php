@extends($side === 'staff' ? 'internal.layout' : 'layouts.app', ['title' => 'Order operations'])

@section('content')
@php
    $page = $side === 'staff' ? 'internal.order-operations' : 'orders.operations';
    $submit = route($page.'.submit', $order);
    $label = fn ($value) => ucfirst(str_replace('_', ' ', $value));
@endphp
<main class="purchase-page">
    <div class="purchase-heading"><h1>Order operations</h1><p>{{ $order->reference }} · {{ $order->product_name }} · {{ $label($order->status) }}</p>
        @if($side === 'buyer')
            <a class="text-link" href="{{ route('orders.show', $order) }}">Back to order</a>
        @elseif($side === 'merchant')
            <a class="text-link" href="{{ route('merchant.orders.all') }}">Back to merchant orders</a>
        @else
            <a class="text-link" href="{{ route('internal.order-cases') }}">All order cases</a>
        @endif
    </div>
    @include('auth.messages')
    @if($canCancel || $canOpen)
        <form method="POST" action="{{ $submit }}" class="purchase-section">
            @csrf
            <h2>{{ $canCancel ? 'Cancel unpaid order' : 'Open a case' }}</h2>
            @if($canCancel)
                <input type="hidden" name="action" value="cancel">
                <p>Cancelling releases any reserved stock immediately.</p>
            @else
                <div class="field"><label for="operation">Case type</label><select id="operation" name="action"><option value="cancellation">Paid order cancellation request</option><option value="refund">Refund request</option><option value="dispute">Dispute</option></select></div>
            @endif
            <div class="field"><label for="reason">Reason shared with the buyer, merchant and reviewer</label><textarea id="reason" name="note" rows="3" minlength="5" maxlength="3000" required></textarea></div>
            <button class="secondary-button">{{ $canCancel ? 'Cancel order' : 'Submit case' }}</button>
        </form>
    @endif
    <section class="purchase-section">
        <h2>Cases for this order</h2>
        @forelse($cases as $case)
            <p><a class="text-link" href="{{ route($page, [$order, 'case' => $case->id]) }}">Case {{ $case->id }} · {{ $label($case->kind) }} · {{ $label($case->status) }}</a></p>
        @empty
            <p>No cases have been opened.</p>
        @endforelse
        {{ $cases->links() }}
    </section>
    @if($selected)
        <section class="purchase-section">
            <h2>Case {{ $selected->id }} · {{ $label($selected->kind) }}</h2>
            <p>Status {{ $label($selected->status) }}</p>
            @if($side !== 'merchant' && $selected->refund_minor > 0 && $selected->status !== 'dismissed')
                <p>Refund amount KES {{ number_format($selected->refund_minor / 100, 2) }} · Units to restock {{ $selected->restock_quantity }}</p>
            @endif
            @if($side === 'staff' && $selected->refund_reference)
                <p>External refund reference {{ $selected->refund_reference }}</p>
            @endif
            @foreach($events as $event)
                <article class="internal-review-event"><strong>{{ $label($event->actor_type) }} · {{ $label($event->action) }}</strong>
                    <time>{{ $event->created_at->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT</time>
                    <p>{{ $event->note }}</p>
                </article>
            @endforeach
            {{ $events->links() }}
        </section>
        @if($selected->status === 'open')
            <form class="purchase-section" method="POST" action="{{ $submit }}">
                @csrf
                <input type="hidden" name="action" value="evidence"><input type="hidden" name="case_id" value="{{ $selected->id }}">
                <div class="field"><label for="evidence">Evidence or additional details</label><textarea id="evidence" name="note" minlength="5" maxlength="3000" rows="3" required></textarea></div>
                <button class="secondary-button">Add evidence</button>
            </form>
        @endif
        @if($side === 'staff' && ($selected->status === 'open' || ($selected->status === 'refund_approved' && $actor->isSystemAdmin())))
            <form class="purchase-section" method="POST" action="{{ $submit }}">
                @csrf
                <h2>Review decision</h2>
                <input type="hidden" name="action" value="decide"><input type="hidden" name="case_id" value="{{ $selected->id }}">
                <div class="field"><label for="decision">Decision</label><select id="decision" name="decision"><option value="dismiss">Close without a refund</option>
                    @if($selected->status === 'open')<option value="approve_refund">Approve a refund</option>
                    @else<option value="record_refund">Record completed refund</option>@endif
                </select></div>
                @if($selected->status === 'open')
                    <div class="field"><label for="amount">Refund amount in cents for approval (100 cents = KES 1)</label><input id="amount" name="refund_minor" type="number" min="1" max="{{ $order->total_minor }}"></div>
                    <div class="field"><label for="restock">Units physically recovered or never dispatched</label><input id="restock" name="restock_quantity" type="number" value="0" min="0" max="{{ $order->quantity }}"></div>
                @elseif($order->payment_status !== 'test_paid')
                    <div class="field"><label for="refund-reference">Verified external refund reference</label><input id="refund-reference" name="reference" maxlength="100"></div>
                    <p>Record this only after verifying the refund externally. Saving this form sends no payment.</p>
                @else
                    <p>Recording this test refund moves no funds.</p>
                @endif
                <div class="field"><label for="decision-note">Decision explanation shared with both parties</label><textarea id="decision-note" name="note" minlength="5" maxlength="3000" rows="3" required></textarea></div>
                <button class="secondary-button">Save decision</button>
            </form>
        @endif
    @endif
</main>
@endsection
