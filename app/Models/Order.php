<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];
    // Never serialize an order into a public response. Use the audience presenters.
    protected $hidden = ['buyer_hash', 'buyer_details', 'fulfillment_details', 'buyer_fee_minor', 'total_minor', 'buyer_fee_policy_id', 'buyer_fee_rate_bps'];

    protected function casts(): array
    {
        return ['buyer_details' => 'encrypted:array', 'fulfillment_details' => 'encrypted:array', 'consented_at' => 'immutable_datetime', 'buyer_confirmed_at' => 'immutable_datetime', 'delivery_issue_open' => 'boolean', 'stock_reserved_until' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function scopeConfirmedPayments(Builder $query): Builder
    {
        return $query->join('payment_collections as payment', 'payment.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->where('payment.status', 'confirmed')
            ->whereColumn('payment.amount_minor', 'orders.total_minor')
            ->whereNotNull('payment.provider_reference');
    }

    public function payment()
    {
        return $this->hasOne(PaymentCollection::class);
    }

    public function finance()
    {
        return $this->hasOne(MerchantOrderFinance::class);
    }
}
