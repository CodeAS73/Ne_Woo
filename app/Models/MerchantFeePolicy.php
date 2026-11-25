<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantFeePolicy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tiers'=>'array'];
    }
}
