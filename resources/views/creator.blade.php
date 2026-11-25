@extends('layouts.app')
@section('content')
<main class="creator-page">
    <section class="hero">
        <div class="hero-content"><div class="eyebrow"><span></span> BIG POSSIBILITIES FOR SMALL BUSINESSES</div><h1>Your next sale starts<br>with <span>a little widget.</span></h1><p>Add your product. Make it yours. Share it anywhere.<br>A beautiful buying experience, one link away.</p><div class="hero-benefits"><span>✓ Free to create</span><span>✓ No website needed</span><span>✓ Ready to share</span></div></div>
        <div class="hero-art" aria-hidden="true"><div class="art-orbit"></div><span class="floating-label label-top">Made to be shared <span>✦</span></span><div class="mini-shopping-card"><div class="mini-image"><img src="{{ asset('images/weekender.svg') }}" alt=""></div><div><span>Your next bestseller</span><strong>One product.<br>Endless possibilities.</strong><div class="mini-colors"><i></i><i></i><i></i></div></div></div><span class="floating-label label-bottom"><span class="green-circle">✓</span> From a scroll to a sale</span><span class="art-star">✳</span></div>
    </section>
    <section class="creator-section" id="creator">
        <div class="section-heading"><div><span class="eyebrow section-eyebrow">LET’S MAKE SOMETHING SELL</span><h2>Create your product widget</h2><p>A few details are all you need. Your preview updates as you go.</p></div><span class="free-label">✦ Always free to create</span></div>
        @if(!empty($merchantOrdersUrl))<div class="merchant-access-link"><a href="{{ $merchantOrdersUrl }}">View orders for your saved product</a></div>@endif
        @if(session('success'))<div class="notice success-notice" role="status">✓ {{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error-notice" role="alert"><strong>Let’s fix a few details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Please select your photos again before saving.</p></div>@endif
        @if($canCreate)
        <div class="workspace" data-creator data-created='@json($created)'>
            <form class="editor-panel" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" id="product-form">
                @csrf
                <div class="panel-title"><span class="step-icon">✎</span><div><h3>Make it yours</h3><p>Tell buyers what makes your product special.</p></div><span class="panel-tag">Product details</span></div>
                <div class="editor-body">
                    <label class="field-label" for="photos">Product photos <span>Up to 5 photos</span></label>
                    <label class="upload-zone" for="photos"><span class="upload-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="m3 16 5-5 5 6 3-3 5 5M15 2v6m-3-3h6"/></svg></span><strong>Drop your photos here, or <u>browse files</u></strong><span>JPG, PNG or WebP · Up to 5 MB each</span><input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required></label>
                    <div class="upload-thumbnails" data-upload-thumbnails></div><p class="input-help" data-upload-help>The first photo is your cover. Choose clear, well-lit images.</p>
                    <div class="field"><label for="name">Product name <span class="required-mark">*</span></label><input id="name" name="name" maxlength="120" placeholder="e.g. Everyday canvas weekender" value="{{ old('name') }}" required><div class="field-footer"><span>Give your product a name worth remembering.</span><span data-name-count>0/120</span></div></div>
                    <div class="field"><label for="description">Brief description <span class="required-mark">*</span></label><textarea id="description" name="description" rows="3" maxlength="600" placeholder="What makes it special? Tell your buyers in a few words." required>{{ old('description') }}</textarea><div class="field-footer"><span>Keep it short, helpful and full of personality.</span><span data-description-count>0/600</span></div></div>
                    <div class="form-row"><div class="field"><label for="price">Normal price <span class="required-mark">*</span></label><div class="money-input"><span>KES</span><input id="price" name="price" type="number" step="0.01" min="1" max="99999999" placeholder="4,600" value="{{ old('price') }}" required></div></div><div class="field"><label for="offer">Offer price <span class="optional">Optional</span></label><div class="money-input"><span>KES</span><input id="offer" name="offer" type="number" step="0.01" min="0.01" placeholder="3,450" value="{{ old('offer') }}"></div></div></div>
                    <div class="field"><label for="stock_quantity">Stock available <span class="required-mark">*</span></label><input id="stock_quantity" name="stock_quantity" type="number" min="0" max="1000000" step="1" placeholder="e.g. 25" value="{{ old('stock_quantity') }}" required><p class="input-help">Units available to sell. You can amend this later from your product’s stock settings.</p></div>
                    <label class="check-option"><input name="show_stock_level" type="hidden" value="0"><input id="show_stock_level" name="show_stock_level" type="checkbox" value="1" @checked(old('show_stock_level', true))> Show stock count to buyers</label><p class="input-help">When hidden, buyers see only “In stock” or “Out of stock”.</p>
                    <div class="offer-tip"><span>✦</span> A great offer deserves the spotlight. We’ll highlight your discount.</div>
                    <div class="field flash-field"><label for="offer_ends_at">Make it a flash sale <span class="optional">Optional</span></label><input id="offer_ends_at" name="offer_ends_at" type="datetime-local" value="{{ old('offer_ends_at') }}"><p class="input-help">Set an offer end time in East Africa Time. We’ll add a countdown.</p></div>
                    <section class="merchant-delivery-settings" data-delivery-settings>
                        <h3>How will buyers receive it?</h3><p class="input-help">Offer pickup, delivery, or both. Delivery is charged once per order.</p>
                        <label class="check-option"><input type="hidden" name="pickup_enabled" value="0"><input type="checkbox" name="pickup_enabled" value="1" data-pickup-enabled @checked(old('pickup_enabled', true))> Allow pickup</label>
                        <div class="field" data-pickup-fields><label for="pickup_details">Pickup location &amp; instructions</label><textarea id="pickup_details" name="pickup_details" maxlength="600" rows="2" placeholder="e.g. Shop 4, Moi Avenue, Nairobi. Mon–Sat, 9am–5pm.">{{ old('pickup_details') }}</textarea></div>
                        <label class="check-option"><input type="hidden" name="delivery_enabled" value="0"><input type="checkbox" name="delivery_enabled" value="1" data-delivery-enabled @checked(old('delivery_enabled', false))> Offer delivery</label>
                        <div data-delivery-fields>
                            <div class="field"><label for="delivery_fee">Delivery charge per order (KES)</label><input id="delivery_fee" name="delivery_fee" type="number" min="0" max="999999" step="0.01" value="{{ old('delivery_fee', '0') }}"><p class="input-help">Enter 0 for free delivery.</p></div>
                            <div class="field"><label for="delivery_areas">Delivery areas</label><input id="delivery_areas" name="delivery_areas" maxlength="2000" value="{{ old('delivery_areas') }}" placeholder="Nairobi, Kiambu, Mombasa"><p class="input-help">Separate areas with commas. Buyers can only select these areas.</p></div>
                            <div class="field"><label for="delivery_estimate">Delivery estimate</label><input id="delivery_estimate" name="delivery_estimate" maxlength="120" value="{{ old('delivery_estimate') }}" placeholder="e.g. 1–3 business days"></div>
                        </div>
                    </section>
                    <button class="primary-button create-button" type="submit"><span>✦</span> Create my widget</button>
                    <p class="save-note">Your approved merchant account owns every widget you create.</p>
                </div>
            </form>
            <aside class="preview-column" aria-label="Product preview and sharing">
                <div class="preview-heading"><h3>Your live preview</h3><span><i></i> Updates as you type</span></div>
                <div class="preview-stage"><div class="preview-topline"><span class="preview-dot"></span><span data-preview-caption>{{ $created ? 'Your saved product' : 'Example product · make it yours' }}</span><span class="preview-dots">•••</span></div>@include('partials.product-card')<p class="preview-stage-note">A little preview of your next big sale.</p></div>
                <section class="sharing-panel" id="sharing"><div class="sharing-title"><h3>One product. Three ways to share.</h3><p>Meet your buyers wherever they are.</p></div><div class="share-tabs" role="tablist" aria-label="Share format"><button type="button" role="tab" aria-selected="true" data-tab="widget">▧ Widget</button><button type="button" role="tab" aria-selected="false" data-tab="button">▣ Sale button</button><button type="button" role="tab" aria-selected="false" data-tab="link">↗ Product link</button></div><div class="share-content"><p data-share-description>Embed a complete product card on your website or blog.</p><div class="button-sample" hidden><span class="sample-sale-button">Buy now on Ne_Woo</span></div><label class="sr-only" for="share-code">Share code or link</label><textarea id="share-code" readonly rows="3" placeholder="Create your widget to get your embed code."></textarea><button type="button" class="copy-button" data-copy disabled>Copy embed code</button><div class="share-destinations"><span>Or share directly</span><a data-share-whatsapp aria-disabled="true" class="social-circle" aria-label="Share on WhatsApp">WA</a><a data-share-facebook aria-disabled="true" class="social-circle" aria-label="Share on Facebook">f</a><a data-share-x aria-disabled="true" class="social-circle" aria-label="Share on X">𝕏</a><a data-share-email aria-disabled="true" class="social-circle" aria-label="Share by email">✉</a><a data-share-sms aria-disabled="true" class="social-circle" aria-label="Share by SMS">SMS</a></div><p class="share-help" data-share-help>Create your widget to unlock sharing.</p></div></section>
            </aside>
        </div>
        @else
        <div class="merchant-signup-gate">
            <div><span class="eyebrow">YOUR BUSINESS STARTS HERE</span><h2>Ready to create your first widget?</h2><p>Register as a merchant and submit your KYC details. Once approved, your product creator will be ready to go.</p><div class="review-actions">
            @if(auth('web')->check() && auth('web')->user()->account_type === 'merchant')<a class="primary-button" href="{{ route('kyc.form') }}">View merchant verification</a><a class="secondary-button" href="{{ route('account.dashboard') }}">My account</a>
            @elseif(auth('web')->check())<a class="primary-button" href="{{ route('account.dashboard') }}">My buyer account</a><span>Merchant widgets require a merchant account.</span>
            @else<a class="primary-button" href="{{ route('register', ['type' => 'merchant']) }}">Sign up as a merchant</a><a class="secondary-button" href="{{ route('login') }}">Already a merchant? Sign in</a>@endif
            </div><p class="input-help">Just shopping? You can buy as a guest or <a class="text-link" href="{{ route('register', ['type' => 'buyer']) }}">create a buyer account</a>.</p></div>
            <div class="signup-product-art" aria-hidden="true"><img src="{{ asset('images/weekender.svg') }}" alt=""><span>Products worth sharing.</span></div>
        </div>
        @endif
    </section>
    @includeWhen($discoveryCards !== [], 'partials.product-discovery')
    <section class="how-section" id="how-it-works"><div class="how-heading"><span class="eyebrow section-eyebrow">LESS SETUP. MORE SELLING.</span><h2>From your product to their next favourite.</h2></div><div class="how-grid"><article><span class="how-icon orange-icon">▧</span><div><h3>Add your product</h3><p>A few photos, a price and a little story.<br>That’s all it takes to get started.</p></div></article><article><span class="how-icon green-icon">↗</span><div><h3>Share it anywhere</h3><p>Your website, social feed or a simple<br>message. Your shop goes with you.</p></div></article><article><span class="how-icon yellow-icon">♡</span><div><h3>Make buying feel easy</h3><p>One clear product view. One simple<br>way to start a purchase.</p></div></article></div></section>
</main>
@endsection
