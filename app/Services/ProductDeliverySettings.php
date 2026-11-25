<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductDeliverySettings
{
    public function validate(Request $request): array
    {
        $data = $request->validate([
            'delivery_enabled' => ['sometimes', 'boolean'],
            'pickup_enabled' => ['sometimes', 'boolean'],
            'delivery_fee' => ['nullable', 'required_if:delivery_enabled,1', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
            'delivery_areas' => ['nullable', 'required_if:delivery_enabled,1', 'string', 'max:2000'],
            'delivery_estimate' => ['nullable', 'required_if:delivery_enabled,1', 'string', 'max:120'],
            'pickup_details' => ['nullable', 'required_if:pickup_enabled,1', 'string', 'max:600'],
        ]);
        $delivery = $request->boolean('delivery_enabled');
        $pickup = $request->boolean('pickup_enabled', true);
        if (! $delivery && ! $pickup) {
            throw ValidationException::withMessages(['delivery_enabled' => 'Offer delivery, pickup, or both so buyers can receive this product.']);
        }
        $areas = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['delivery_areas'] ?? '')))));
        if ($delivery && (! count($areas) || count($areas) > 47 || max(array_map('strlen', $areas)) > 80)) {
            throw ValidationException::withMessages(['delivery_areas' => 'Enter up to 47 delivery areas, each up to 80 characters, separated by commas.']);
        }
        [$whole, $fraction] = array_pad(explode('.', (string) ($data['delivery_fee'] ?? 0), 2), 2, '');

        return [
            'delivery_enabled' => $delivery, 'pickup_enabled' => $pickup,
            'delivery_fee_minor' => $delivery ? (int) $whole * 100 + (int) str_pad($fraction, 2, '0') : 0,
            'delivery_areas' => $delivery ? $areas : [],
            'delivery_estimate' => $delivery ? $data['delivery_estimate'] : null,
            'pickup_details' => $pickup ? ($data['pickup_details'] ?? 'Arrange collection with the merchant.') : null,
        ];
    }
}
