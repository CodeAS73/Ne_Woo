@php
    $display = $product ?? $created ?? [
        'name' => 'Everyday canvas weekender',
        'description' => 'Your everyday carry, upgraded. A spacious canvas bag with warm leather details, ready for work, weekends and everything in between.',
        'price' => 4600, 'currentPrice' => 3450, 'stock' => 25,
        'photos' => [asset('images/weekender.svg')],
        'buyUrl' => '#',
    ];
    $discounted = $display['currentPrice'] < $display['price'];
    $inStock = $display['inStock'] ?? (($display['stock'] ?? 25) > 0);
@endphp
<article class="product-card" data-product-card>
    <div class="product-image-wrap">
        <span class="product-discount" data-discount @if(!$discounted) hidden @endif>-{{ $discounted ? round((1 - $display['currentPrice'] / $display['price']) * 100) : 0 }}%</span>
        <button class="main-photo-button" type="button" aria-label="Enlarge product photo"><img class="main-photo" data-main-photo src="{{ $display['photos'][0] ?? asset('images/weekender.svg') }}" alt="{{ $display['name'] }}"></button>
        <span class="image-hint">⌕ Click to explore</span>
    </div>
    <div class="photo-thumbnails" data-thumbnails></div>
    <div class="product-information">
        <div class="product-brand"><span class="mini-brand">ne_woo</span><span>Product spotlight</span></div>
        <h3 data-product-name>{{ $display['name'] }}</h3>
        <p class="product-description" data-product-description>{{ $display['description'] }}</p>
        <p class="product-stock-label" data-stock-label>{{ $inStock ? (isset($display['stock']) ? 'In stock · '.$display['stock'].' available' : 'In stock') : 'Out of stock' }}</p>
        <div class="price-row"><strong data-current-price>KES {{ number_format($display['currentPrice'], 2) }}</strong><s data-original-price @if(!$discounted) hidden @endif>KES {{ number_format($display['price'], 2) }}</s></div>
        <div class="sale-clock" data-sale-clock hidden><span>✦ Offer ends in</span><strong data-countdown></strong></div>
        <a class="buy-button" data-buy-link href="{{ $display['buyUrl'] }}" target="_top" aria-disabled="{{ $display['buyUrl'] === '#' || !$inStock ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 7h14l-2 9H8L5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg><span data-buy-label>{{ !$inStock ? 'Out of stock' : 'Buy now' }}</span></a>
        <div class="card-assurance"><span>✓ No app download needed</span><span>Powered by <b>ne_woo</b></span></div>
    </div>
</article>
