<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutQuote extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['details', 'buyer_hash', 'pricing'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'pricing' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
