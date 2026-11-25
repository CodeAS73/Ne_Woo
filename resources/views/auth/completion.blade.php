@extends($internal ? 'internal.layout' : 'layouts.app', ['title' => $title])

@section('content')
<main class="purchase-page">
    <form class="auth-card" method="POST" action="{{ route(($internal ? 'internal.' : '').'completion.'.($token ? 'redeem' : 'send'), array_filter(compact('purpose', 'token'))) }}">
        @csrf
        <h1>{{ $title }}</h1>
        @include('auth.messages')
        @if($token && $purpose === 'reset-password')
            <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required></div>
            <div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
        @elseif(!$token && $purpose !== 'verify-email')
            @if($purpose === 'recover-order')
                <div class="field"><label for="reference">Order reference</label><input id="reference" name="reference" value="{{ old('reference') }}" required></div>
            @endif
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required></div>
        @elseif(!$token)
            <p>We’ll send a confirmation link to the email address on your account.</p>
        @endif
        <button class="primary-button" type="submit">{{ $token ? 'Confirm' : 'Send email' }}</button>
    </form>
</main>
@endsection
