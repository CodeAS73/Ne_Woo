@extends('layouts.app', ['title' => $product['name'], 'metaDescription' => $product['description'], 'metaImage' => $product['photos'][0] ?? null])
@section('content')
<main class="{{ $embedded ? 'embed-page' : 'product-page' }}" data-public-product data-product='@json($product)'>
    @unless($embedded)<div class="product-page-heading"><span class="eyebrow section-eyebrow">A GREAT FIND, JUST FOR YOU</span><h1>A little closer to your next favourite.</h1><p>Explore the details, pick your quantity and review your purchase.</p></div>@endunless
    @include('partials.product-card')
    @if(!empty($merchantDeliveryUrl))<div class="product-owner-tools"><span>Your product controls</span><a class="merchant-share-link" href="{{ $merchantShareUrl }}">Share product</a><a href="{{ $merchantStockUrl }}">Manage stock</a><a href="{{ $merchantDeliveryUrl }}">Delivery &amp; pickup settings</a><a href="{{ $merchantOrdersUrl }}">View product orders</a></div>@endif
    @unless($embedded)<div class="public-note">Development product preview · Payments are not available yet.</div>@endunless
</main>
@endsection
