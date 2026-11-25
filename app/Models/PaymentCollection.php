<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCollection extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['phone', 'idempotency_key', 'provider_reference'];

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'confirmed_at' => 'immutable_datetime'];
    }
}
