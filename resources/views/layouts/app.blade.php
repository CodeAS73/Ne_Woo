<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? 'Turn your products into beautiful, shareable shopping experiences with Ne_Woo.' }}">
    <meta property="og:title" content="{{ $title ?? 'Create your product widget' }} · Ne_Woo">
    <meta property="og:description" content="{{ $metaDescription ?? 'Beautiful product widgets. Create for free and share anywhere.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Ne_Woo">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Ne_Woo product widgets' }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? 'Discover products on Ne_Woo.' }}">
    @isset($metaImage)
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:image:alt" content="{{ $title ?? 'Product photo' }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    <meta name="twitter:image:alt" content="{{ $title ?? 'Product photo' }}">
    @endisset
    <title>{{ $title ?? 'Create your product widget' }} · Ne_Woo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@unless($embedded ?? false)
    <div class="announcement"><span class="tiny-spark">✦</span> Your products. Everywhere. <span class="announcement-extra">Create and share product widgets for free.</span></div>
    <header class="site-header">
        <div class="header-inner">
            <a href="{{ route('creator') }}" class="brand" aria-label="Ne Woo home">ne<span class="brand-mark">_</span>woo<span class="brand-dot">●</span></a>
            <nav aria-label="Main navigation"><a href="{{ route('creator') }}" class="nav-active">Widget creator</a><a href="{{ route('creator') }}#how-it-works">How it works</a></nav>
            <div class="public-account-nav">@if(auth('web')->check())<a href="{{ route('account.dashboard') }}">My account</a><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Sign out</button></form>@else<a href="{{ route('login') }}">Sign in</a><a class="signup-nav-button" href="{{ route('register') }}">Create account</a>@endif</div>
        </div>
    </header>
@endunless
@yield('content')
@unless($embedded ?? false)
    <footer class="footer"><a class="brand small-brand" href="{{ route('creator') }}">ne<span class="brand-mark">_</span>woo<span class="brand-dot">●</span></a><span>A little widget. A world of possibility.</span><span>Built for merchants in Kenya · KES</span></footer>
@endunless
<div class="toast" role="status" aria-live="polite" hidden></div>
<dialog class="photo-dialog"><button type="button" class="dialog-close" aria-label="Close enlarged photo">✕</button><img alt="Enlarged product photo"></dialog>
</body>
</html>
