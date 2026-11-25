<section class="purchase-section delivery-progress">
    <div class="purchase-section-heading"><h2>Delivery &amp; receipt</h2><span class="delivery-pill">{{ ucwords(str_replace('_', ' ', $workflow['status'])) }}</span></div>
    @if($workflow['test'])<p class="workflow-test">Local test order · No funds can be released.</p>@endif
    @if(!$workflow['funded'])<p>Fulfilment starts after payment is confirmed.</p>
    @elseif($workflow['issue_open'])<div class="notice error-notice">A delivery issue is open. Settlement is on hold for internal review.</div>
    @elseif($workflow['buyer_confirmed'])<div class="notice success-notice">Receipt confirmed by the buyer.</div>
    @else<p>Only confirm receipt after you have actually received your product.</p>@endif
    <ol class="delivery-timeline">@foreach($workflow['events'] as $event)<li><strong>{{ ucwords(str_replace('_', ' ', $event['action'])) }}</strong><time>{{ $event['at'] }}</time>@if($event['note'])<p>{{ $event['note'] }}</p>@endif</li>@endforeach</ol>
    @if($workflow['buyer_confirmed'])<p>Internal review: <strong>{{ ucwords(str_replace('_', ' ', $workflow['review_status'])) }}</strong>. Approval does not mean a payout has been sent.</p>@endif
</section>
