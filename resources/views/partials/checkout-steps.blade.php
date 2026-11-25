<ol class="checkout-steps" aria-label="Checkout progress">
    @foreach(['Details', 'Review', 'Payment'] as $index => $label)
        <li class="{{ $step === $index + 1 ? 'current' : ($step > $index + 1 ? 'complete' : '') }}" @if($step === $index + 1) aria-current="step" @endif><span>{{ $step > $index + 1 ? '✓' : $index + 1 }}</span>{{ $label }}</li>
    @endforeach
</ol>
