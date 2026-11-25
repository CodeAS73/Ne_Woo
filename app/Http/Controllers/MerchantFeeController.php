<?php

namespace App\Http\Controllers;

use App\Models\MerchantFeePolicy;
use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MerchantFeeController extends Controller
{
    public function index()
    {
        $id = DB::table('merchant_fee_settings')->where('id',1)->value('active_policy_id');
        $policy = $id ? MerchantFeePolicy::findOrFail($id) : null;
        $submitted = old('tiers');
        $formTiers = is_array($submitted) ? array_slice($submitted,0,20,true) : collect($policy?->tiers ?? [])->map(fn ($tier) => ['basis'=>$tier['basis'], 'threshold'=>$tier['basis'] === 'monthly_units' ? $tier['threshold'] : $tier['threshold']/100, 'rate'=>$tier['rate_bps']/100])->all();
        return view('internal.merchant-fees', ['policy'=>$policy, 'formTiers'=>$formTiers, 'history'=>MerchantFeePolicy::latest('id')->paginate(10)]);
    }

    public function publish(Request $request)
    {
        $data = $request->validate([
            'base_rate'=>['required','numeric','min:0','max:5','decimal:0,2'],
            'tiers'=>['nullable','array','max:20'],
            'tiers.*.basis'=>['required',Rule::in(['monthly_units','monthly_value','unit_value'])],
            'tiers.*.threshold'=>['required','numeric','min:0.01','max:999999999','decimal:0,2'],
            'tiers.*.rate'=>['required','numeric','min:0','max:5','lte:base_rate','decimal:0,2'],
        ]);
        $tiers=[];
        foreach ($data['tiers'] ?? [] as $index=>$tier) {
            if ($tier['basis'] === 'monthly_units' && ! preg_match('/^\d+(?:\.0{1,2})?$/',(string)$tier['threshold'])) {
                throw ValidationException::withMessages(["tiers.$index.threshold"=>'Monthly unit thresholds must be whole numbers.']);
            }
            if ($tier['basis'] === 'unit_value' && $this->hundredths((string)$tier['threshold']) <= 1000000) {
                throw ValidationException::withMessages(["tiers.$index.threshold"=>'High-value item tiers must start above KES 10,000.']);
            }
            $tiers[]=['basis'=>$tier['basis'],'threshold'=>$tier['basis'] === 'monthly_units' ? (int)$tier['threshold'] : $this->hundredths((string)$tier['threshold']), 'rate_bps'=>$this->hundredths((string)$tier['rate'])];
        }
        DB::transaction(function () use ($data,$tiers) {
            DB::table('merchant_fee_settings')->where('id',1)->lockForUpdate()->first();
            $policy=MerchantFeePolicy::create(['base_rate_bps'=>$this->hundredths((string)$data['base_rate']), 'tiers'=>$tiers, 'created_by'=>Auth::guard('internal')->id()]);
            DB::table('merchant_fee_settings')->where('id',1)->update(['active_policy_id'=>$policy->id]);
            SecurityAudit::record('fees.policy_published','merchant_fee_policy',$policy->id);
        });
        return redirect()->route('internal.merchant-fees')->with('success','Merchant fee policy published. Existing placed orders keep their agreed fees.');
    }

    private function hundredths(string $value): int
    {
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        return (int)$whole*100+(int)str_pad($fraction,2,'0');
    }
}
