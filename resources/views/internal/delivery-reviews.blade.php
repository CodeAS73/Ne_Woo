@extends('internal.layout', ['title' => 'Delivery and settlement review'])
@section('content')
<main class="purchase-page"><div class="purchase-heading"><span class="eyebrow">DELIVERY CONFIRMATION &amp; SUPERVISOR REVIEW</span><h1>Review before release.</h1><p>Buyer-confirmed orders and delivery holds. Approval records eligibility; scheduled payouts are not enabled.</p></div>@include('auth.messages')
<nav class="workflow-filters" aria-label="Review filters">@foreach(['pending'=>'Awaiting review','on_hold'=>'On hold','approved'=>'Approved','all'=>'All reviews'] as $value=>$label)<a href="{{ route('internal.delivery-reviews', ['filter'=>$value]) }}" @if($filter === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@forelse($rows as $row)<article class="purchase-section review-order-card"><div class="purchase-section-heading"><h2>{{ $row['name'] }}</h2><span class="delivery-pill">{{ ucwords(str_replace('_',' ',$row['workflow']['review_status'])) }}</span></div><p class="order-reference">{{ $row['reference'] }} · {{ ucfirst($row['method']) }}</p>
    @include('partials.delivery-progress', ['workflow'=>$row['workflow']])
    @if($row['review_note'])<div class="internal-review-note"><strong>Latest internal note</strong><p>{{ $row['review_note'] }}</p></div>@endif
    @foreach($row['events'] as $event)<div class="internal-review-event"><strong>{{ ucwords(str_replace('_',' ',$event['action'])) }}</strong><time>{{ $event['at'] }}</time><p>{{ $event['note'] }}</p></div>@endforeach
    <form method="POST" action="{{ route('internal.delivery-review', $row['reference']) }}">@csrf<div class="field"><label for="note-{{ $loop->index }}">Internal review note</label><textarea name="note" id="note-{{ $loop->index }}" minlength="5" maxlength="1000" rows="2" required placeholder="Record the checks made or the reason for this decision."></textarea><p class="input-help">Private to authorized internal reviewers.</p></div><div class="review-actions">
    @if($row['workflow']['issue_open'])<button class="secondary-button" name="decision" value="resolve_issue">Resolve delivery issue</button>
    @elseif($row['workflow']['buyer_confirmed'] && $row['workflow']['review_status'] !== 'approved')<button class="primary-button" name="decision" value="approve">Approve for future payout</button>@endif
    <button class="secondary-button" name="decision" value="hold">Keep on hold</button></div></form>
</article>@empty<div class="notice">No orders in this review category yet. Confirmed deliveries will appear here.</div>@endforelse{{ $pagination }}</main>
@endsection
