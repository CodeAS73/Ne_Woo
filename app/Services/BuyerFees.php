<?php

namespace App\Services;

use App\Models\BuyerFeePolicy;
use Illuminate\Support\Facades\DB;

class BuyerFees
{
    public function quote(int $subtotal): array
    {
        $settings=DB::table('buyer_fee_settings')->where('id',1);
        if (DB::transactionLevel() > 0) {
            $settings->lockForUpdate();
        }
        $policy=BuyerFeePolicy::findOrFail($settings->first()->active_policy_id);
        $fee=intdiv($subtotal*$policy->rate_bps+5000,10000);
        return ['amount'=>max($policy->minimum_minor,min($policy->maximum_minor,$fee)), 'rate'=>$policy->rate_bps,'policy_id'=>$policy->id];
    }
}
