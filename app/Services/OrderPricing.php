<?php

namespace App\Services;

use App\Models\ProductWidget;
use Illuminate\Validation\ValidationException;

class OrderPricing
{
    public function calculate(ProductWidget $product, int $quantity, string $method): array
    {
        if (($method === 'delivery' && ! $product->delivery_enabled) || ($method === 'pickup' && ! $product->pickup_enabled)) {
            throw ValidationException::withMessages(['fulfillment_method' => 'This merchant does not offer the selected delivery or pickup option.']);
        }
        $unit = $product->currentPrice();
        $subtotal = $unit * $quantity;
        $delivery = $method === 'delivery' ? $product->delivery_fee_minor : 0;
        // These values are set by internal configuration, never accepted from checkout input.
        $buyerPolicy = app(BuyerFees::class)->quote($subtotal);
        $buyerFee = $buyerPolicy['amount'];
        $fee = app(MerchantFees::class)->quote($product,$quantity,$unit,$subtotal);
        $rate = $fee['rate'];
        $leadFee = intdiv($subtotal * $rate + 5000, 10000);

        return [
            'unit_price_minor' => $unit, 'subtotal_minor' => $subtotal,
            'delivery_minor' => $delivery, 'buyer_fee_minor' => $buyerFee,
            'total_minor' => $subtotal + $delivery + $buyerFee,
            'buyer_fee_policy_id'=>$buyerPolicy['policy_id'], 'buyer_fee_rate_bps'=>$buyerPolicy['rate'],
            'lead_fee_basis_points' => $rate, 'lead_fee_minor' => $leadFee,
            'merchant_receivable_minor' => $subtotal + $delivery - $leadFee,
            'fee_policy_version' => $fee['version'], 'fee_rule'=>$fee['rule'],
        ];
    }

    public function fingerprint(ProductWidget $product, array $pricing, string $method): string
    {
        return hash('sha256', json_encode([$pricing, $method, $product->name, $product->delivery_enabled, $product->pickup_enabled, $product->delivery_areas, $product->delivery_estimate, $product->pickup_details], JSON_THROW_ON_ERROR));
    }

    public function buyer(array $pricing): array
    {
        return array_intersect_key($pricing, array_flip(['unit_price_minor', 'subtotal_minor', 'delivery_minor', 'buyer_fee_minor', 'total_minor']));
    }
}
