<?php

namespace App\Services;

use App\Models\MerchantFeePolicy;
use App\Models\Order;
use App\Models\ProductWidget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MerchantFees
{
    public function quote(ProductWidget $product, int $quantity, int $unit, int $subtotal): array
    {
        $settings = DB::table('merchant_fee_settings')->where('id',1);
        if (DB::transactionLevel() > 0) {
            $settings->lockForUpdate();
        }
        $id = $settings->first()?->active_policy_id;
        $policy = $id ? MerchantFeePolicy::findOrFail($id) : null;
        if (! $policy) {
            return ['rate'=>max(0,min(500,(int)config('newoo.fees.merchant_basis_points',0))), 'version'=>(string)config('newoo.fees.policy_version','development-v1'), 'rule'=>'base'];
        }
        $rate = $policy->base_rate_bps;
        $rule = 'base';
        $local = CarbonImmutable::now('Africa/Nairobi');
        $states = app()->environment(['local','testing']) && config('buni.mode') === 'simulator' ? ['paid','test_paid'] : ['paid'];
        $collections = count($states) === 2 ? ['confirmed','test_confirmed'] : ['confirmed'];
        $sales = $product->merchant_user_id ? Order::query()->join('payment_collections as payments','orders.id','=','payments.order_id')
            ->where('orders.merchant_user_id',$product->merchant_user_id)->whereIn('orders.payment_status',$states)->whereIn('payments.status',$collections)->where('payments.provider','kcb_buni')
            ->where(function ($query) use ($states) {
                $query->where(fn ($pair) => $pair->where('orders.payment_status','paid')->where('payments.status','confirmed'));
                if (in_array('test_paid',$states,true)) {
                    $query->orWhere(fn ($pair) => $pair->where('orders.payment_status','test_paid')->where('payments.status','test_confirmed'));
                }
            })
            ->whereColumn('payments.amount_minor','orders.total_minor')->where('orders.currency','KES')
            ->where('payments.confirmed_at','>=',$local->startOfMonth()->utc())->where('payments.confirmed_at','<',$local->startOfMonth()->addMonth()->utc())
            ->selectRaw('COALESCE(SUM(orders.quantity),0) as units, COALESCE(SUM(orders.subtotal_minor),0) as value')->first() : null;
        foreach ($policy->tiers as $index=>$tier) {
            $metric = match ($tier['basis']) {
                'monthly_units' => (int)($sales?->units ?? 0) + $quantity,
                'monthly_value' => (int)($sales?->value ?? 0) + $subtotal,
                'unit_value' => $unit,
            };
            if ($metric >= $tier['threshold'] && $tier['rate_bps'] < $rate) {
                $rate = $tier['rate_bps'];
                $rule = 'tier:'.$index.':'.$tier['basis'];
            }
        }
        return ['rate'=>max(0,min(500,$rate)), 'version'=>'policy-'.$policy->id, 'rule'=>$rule];
    }
}
