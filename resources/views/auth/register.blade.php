@extends('layouts.app', ['title' => 'Create an account'])
@section('content')
<main class="auth-page"><div class="auth-intro"><span class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</span><h1>A home for your next sale.<br>And your next great find.</h1><p>Join as a merchant to share your products, or create a buyer account to keep your orders together.</p><div class="registration-benefits"><span>✓ Free merchant registration</span><span>✓ Private KYC review</span><span>✓ Guest shopping stays available</span></div></div>
<form class="auth-card" action="{{ route('register.store') }}" method="POST">@csrf @include('auth.messages')<h2>Create your account</h2>
    <fieldset class="account-options"><legend>I’d like to</legend><label><input type="radio" name="account_type" value="merchant" @checked(old('account_type', request('type', 'merchant')) === 'merchant') required><strong>Sell as a merchant</strong><span>Submit KYC, then create your widgets.</span></label><label><input type="radio" name="account_type" value="buyer" @checked(old('account_type', request('type')) === 'buyer')><strong>Shop as a buyer</strong><span>Save your orders. No merchant KYC.</span></label></fieldset>
    <div class="field"><label for="name">Full name</label><input id="name" name="name" maxlength="120" autocomplete="name" value="{{ old('name') }}" required></div>
    <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" value="{{ old('email') }}" required></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required><p class="input-help">At least 12 characters, including letters and numbers.</p></div>
    <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
    <button class="primary-button" type="submit">Create account</button><p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p><a class="guest-link" href="{{ route('creator') }}">Continue browsing as a guest</a>
</form></main>
@endsection
