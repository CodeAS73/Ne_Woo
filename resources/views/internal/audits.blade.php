@extends('internal.layout', ['title' => 'Security audit'])
@section('content')
<main class="purchase-page"><div class="purchase-heading"><span class="eyebrow">ACCOUNTABILITY &amp; ACCESS</span><h1>Security audit</h1><p>Account, profile, KYC and ownership events. Passwords, activation tokens and identity-document contents are not logged here.</p></div>@foreach($events as $event)<section class="purchase-section"><h2>{{ $event->action }}</h2><p>Actor: {{ $event->actor_id ?? 'System bootstrap' }} · {{ $event->subject_type }} #{{ $event->subject_id }} · {{ \Carbon\Carbon::parse($event->created_at, 'UTC')->timezone('Africa/Nairobi')->format('d M Y, H:i') }} EAT</p><p>{{ $event->metadata }}</p></section>@endforeach{{ $events->links() }}</main>
@endsection
