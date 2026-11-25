<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantOrderFinance extends Model
{
    protected function casts(): array
    {
        return ['review_note' => 'encrypted', 'reviewed_at' => 'immutable_datetime', 'release_eligible_at' => 'immutable_datetime'];
    }
    protected $guarded = ['id'];
    protected $hidden = ['lead_fee_basis_points', 'lead_fee_minor', 'merchant_receivable_minor', 'fee_policy_version', 'fee_rule'];
}
