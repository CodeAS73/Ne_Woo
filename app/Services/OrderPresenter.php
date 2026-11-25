<?php

namespace App\Services;

use App\Models\MerchantOrderFinance;
use App\Models\Order;

class OrderPresenter
{
    public function buyer(Order $order): array
    {
        return [
            'reference' => $order->reference, 'product_name' => $order->product_name,
            'quantity' => $order->quantity, 'unit_price_minor' => $order->unit_price_minor,
            'subtotal_minor' => $order->subtotal_minor, 'delivery_minor' => $order->delivery_minor,
            'buyer_fee_minor' => $order->buyer_fee_minor, 'total_minor' => $order->total_minor,
            'currency' => $order->currency, 'fulfillment_method' => $order->fulfillment_method,
            'buyer' => $order->buyer_details, 'fulfillment' => $order->fulfillment_details,
            'notification_channel' => $order->notification_channel, 'status' => $order->status,
            'payment_status' => $order->payment_status,
            'stock_status' => $order->stock_status,
        ];
    }

    public function merchant(Order $order): array
    {
        $finance = MerchantOrderFinance::where('order_id', $order->id)->firstOrFail();

        return [
            'reference' => $order->reference, 'product_name' => $order->product_name,
            'quantity' => $order->quantity, 'subtotal_minor' => $order->subtotal_minor,
            'delivery_minor' => $order->delivery_minor,
            'lead_fee_minor' => $finance->lead_fee_minor,
            'merchant_receivable_minor' => $finance->merchant_receivable_minor,
            'settlement_status' => $finance->settlement_status,
            'release_eligible_at' => $finance->release_eligible_at ? $finance->release_eligible_at->timezone('Africa/Nairobi')->format('d M Y, H:i').' EAT' : null,
            'payment_status' => $order->payment_status,
            'fulfillment_method' => $order->fulfillment_method,
            'fulfillment' => $order->fulfillment_details,
            'workflow' => app(DeliveryWorkflow::class)->presentation($order),
        ];
    }
}
