<?php

namespace App\Http\Controllers;

use App\Models\BuyerFeePolicy;
use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerFeeController extends Controller
{
    public function index()
    {
        return view('internal.buyer-fees',['policy'=>BuyerFeePolicy::findOrFail(DB::table('buyer_fee_settings')->where('id',1)->value('active_policy_id')),'history'=>BuyerFeePolicy::latest('id')->paginate(15)]);
    }

    public function publish(Request $request)
    {
        $data=$request->validate(['rate'=>['required','numeric','min:0.01','max:100','decimal:0,2']]);
        [$whole,$fraction]=array_pad(explode('.',(string)$data['rate'],2),2,'');
        $rate=(int)$whole*100+(int)str_pad($fraction,2,'0');
        DB::transaction(function () use ($rate) {
            DB::table('buyer_fee_settings')->where('id',1)->lockForUpdate()->first();
            $policy=BuyerFeePolicy::create(['rate_bps'=>$rate,'minimum_minor'=>3000,'maximum_minor'=>250000,'created_by'=>Auth::guard('internal')->id()]);
            DB::table('buyer_fee_settings')->where('id',1)->update(['active_policy_id'=>$policy->id]);
            SecurityAudit::record('buyer_fees.policy_published','buyer_fee_policy',$policy->id,['rate_bps'=>$rate]);
        });
        return redirect()->route('internal.buyer-fees')->with('success','Buyer secure purchase rate updated. Existing orders retain their agreed fees.');
    }
}
