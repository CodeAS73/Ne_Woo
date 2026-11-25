@extends('layouts.app', ['title' => 'Delivery and pickup settings'])
@section('content')
<main class="purchase-page merchant-delivery-page"><a class="back-link" href="{{ route('products.show', $product) }}">Back to product</a><div class="purchase-heading"><span class="eyebrow">YOUR PRODUCT, YOUR DELIVERY OPTIONS</span><h1>Delivery &amp; pickup</h1><p>{{ $product->name }} · These settings apply to new orders.</p></div>
    @if(session('success'))<div class="notice success-notice" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice error-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form class="purchase-section" action="{{ route('merchant.delivery.update', $product) }}" method="POST">@csrf @method('PUT')<div data-delivery-settings>
        <label class="check-option"><input type="hidden" name="pickup_enabled" value="0"><input type="checkbox" name="pickup_enabled" value="1" data-pickup-enabled @checked(old('pickup_enabled', $product->pickup_enabled))> Allow pickup</label>
        <div class="field" data-pickup-fields><label for="pickup_details">Pickup location &amp; instructions</label><textarea name="pickup_details" id="pickup_details" maxlength="600" rows="3">{{ old('pickup_details', $product->pickup_details) }}</textarea></div>
        <label class="check-option"><input type="hidden" name="delivery_enabled" value="0"><input type="checkbox" name="delivery_enabled" value="1" data-delivery-enabled @checked(old('delivery_enabled', $product->delivery_enabled))> Offer delivery</label>
        <div data-delivery-fields><div class="field"><label for="delivery_fee">Delivery charge per order (KES)</label><input name="delivery_fee" id="delivery_fee" type="number" min="0" step="0.01" max="999999" value="{{ old('delivery_fee', number_format($product->delivery_fee_minor / 100, 2, '.', '')) }}"><p class="input-help">Enter 0 for free delivery. The buyer pays this once in their total.</p></div><div class="field"><label for="delivery_areas">Delivery areas</label><input name="delivery_areas" id="delivery_areas" maxlength="2000" value="{{ old('delivery_areas', implode(', ', $product->delivery_areas ?? [])) }}"><p class="input-help">Separate supported areas with commas.</p></div><div class="field"><label for="delivery_estimate">Delivery estimate</label><input name="delivery_estimate" id="delivery_estimate" maxlength="120" value="{{ old('delivery_estimate', $product->delivery_estimate) }}" placeholder="e.g. 1–3 business days"></div></div>
        <button class="primary-button continue-button" type="submit">Save delivery options</button>
    </div></form>
</main>
@endsection
