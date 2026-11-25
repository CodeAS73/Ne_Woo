@extends($internal ? 'internal.layout' : 'layouts.app', ['title' => $internal ? 'Internal sign in' : 'Sign in'])
@section('content')
<main class="auth-page"><div class="auth-intro"><span class="eyebrow">{{ $internal ? 'NE_WOO INTERNAL' : 'WELCOME BACK' }}</span><h1>{{ $internal ? 'Staff sign in' : 'A little closer to your next sale.' }}</h1><p>{{ $internal ? 'Access is limited to authorized internal accounts.' : 'Sign in to manage your products or keep track of your purchases.' }}</p></div>
<form class="auth-card" action="{{ route($internal ? 'internal.login.store' : 'login.store') }}" method="POST">@csrf
    @include('auth.messages')
    <h2>{{ $internal ? 'Internal account' : 'Sign in to Ne_Woo' }}</h2>
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="primary-button" type="submit">Sign in</button>
    <p><a href="{{ route($internal ? 'internal.completion.form' : 'completion.form', 'reset-password') }}">Forgot your password?</a></p>
    @unless($internal)<p><a href="{{ route('completion.form', 'recover-order') }}">Recover a guest order</a></p>@endunless
    @unless($internal)<p class="auth-switch">New here? <a href="{{ route('register') }}">Create an account</a></p><a class="guest-link" href="{{ route('creator') }}">Browse as a guest</a>@endunless
</form></main>
@endsection
