<section class="product-discovery" aria-labelledby="discovery-heading" data-discovery-carousel>
    <div class="discovery-heading">
        <h2 id="discovery-heading">Discover products</h2>
        <div class="discovery-controls" data-discovery-controls hidden>
            <button type="button" aria-label="Scroll to previous products" aria-controls="discovery-products" data-discovery-step="-1">&#8592;</button>
            <button type="button" aria-label="Scroll to next products" aria-controls="discovery-products" data-discovery-step="1">&#8594;</button>
        </div>
    </div>
    <ul id="discovery-products" class="discovery-track" tabindex="0" aria-label="Product recommendations" data-discovery-track>
        @foreach($discoveryCards as ['product' => $product, 'labels' => $labels])
            @php($price = $product->currentPrice())
            <li>
                <a class="discovery-card" href="{{ route('products.show', $product) }}">
                    @if(isset($product->photos[0]))
                        <img class="discovery-photo" src="{{ route('products.photo', [$product, 0]) }}" alt="" width="70" height="70" loading="lazy" decoding="async">
                    @endif
                    <div class="discovery-details">
                        <div class="discovery-labels">{{ implode(' · ', $labels) }}</div>
                        <h3>{{ $product->name }}</h3>
                        <p class="discovery-price">
                            <strong>KES {{ number_format($price / 100, 2) }}</strong>
                            @if($price < $product->price_minor)
                                <del aria-label="Original price">KES {{ number_format($product->price_minor / 100, 2) }}</del>
                            @endif
                        </p>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
</section>
