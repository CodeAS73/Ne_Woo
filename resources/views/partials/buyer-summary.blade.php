<aside class="buyer-summary">
    <div class="summary-heading"><h2>Order summary</h2><span class="summary-tag">KES</span></div>
    <div class="summary-product"><img src="{{ count($product->photos) ? route('products.photo', [$product, 0]) : asset('images/weekender.svg') }}" alt="{{ $product->name }}"><div><h3>{{ $product->name }}</h3><p><span data-summary-quantity>{{ $quantity }}</span> × KES {{ number_format($cost['unit_price_minor'] / 100, 2) }}</p></div></div>
    <dl class="summary-costs"><div><dt>Product subtotal</dt><dd data-summary-subtotal>KES {{ number_format($cost['subtotal_minor'] / 100, 2) }}</dd></div><div><dt>Delivery</dt><dd data-summary-delivery>{{ $cost['delivery_minor'] ? 'KES '.number_format($cost['delivery_minor'] / 100, 2) : 'Free / pickup' }}</dd></div><div><dt>Secure purchase fee</dt><dd data-summary-buyer-fee>KES {{ number_format($cost['buyer_fee_minor'] / 100, 2) }}</dd></div><div class="summary-total"><dt>Total to pay</dt><dd data-summary-total>KES {{ number_format($cost['total_minor'] / 100, 2) }}</dd></div></dl>
    <div class="single-payment-note"><span>✓</span><p>One payment to <strong>Ne_Woo</strong> for your entire order. No separate payment to the merchant.</p></div>
    <p class="summary-footnote">Review your final total before placing the order. You will never be asked for your M-PESA PIN on this website.</p>
</aside>
