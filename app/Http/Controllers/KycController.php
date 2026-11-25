<?php

namespace App\Http\Controllers;

use App\Models\MerchantKyc;
use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function form()
    {
        $user = Auth::guard('web')->user();
        abort_unless($user?->account_type === 'merchant' && $user->is_active, 403);
        return view('auth.kyc', ['kyc' => $user->kyc, 'details' => $user->kyc?->details ?? []]);
    }

    public function submit(Request $request)
    {
        $user = Auth::guard('web')->user();
        abort_unless($user?->account_type === 'merchant' && $user->is_active, 403);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:120'], 'business_name' => ['required', 'string', 'max:160'],
            'business_type' => ['required', Rule::in(['individual', 'registered'])],
            'registration_number' => ['nullable', 'required_if:business_type,registered', 'string', 'max:80'],
            'id_type' => ['required', Rule::in(['national_id', 'passport'])], 'id_number' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^(?:\+?254|0)[17]\d{8}$/'],
            'business_address' => ['required', 'string', 'max:500'],
            'identity_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'consent' => ['accepted'],
        ]);
        $path = $request->file('identity_document')->store('kyc', 'local');
        try {
            DB::transaction(function () use ($user, $data, $path) {
                // Serialize submissions and reviews on the same merchant record.
                \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = MerchantKyc::where('user_id', $user->id)->first();
                abort_if($existing && in_array($existing->status, ['pending', 'approved'], true), 409, 'Your submitted KYC is already under review or approved.');
                $details = array_intersect_key($data, array_flip(['legal_name', 'business_name', 'business_type', 'registration_number', 'id_type', 'id_number', 'phone', 'business_address']));
                $oldPath = $existing?->document_path;
                MerchantKyc::updateOrCreate(['user_id' => $user->id], ['details' => $details, 'document_path' => $path, 'status' => 'pending', 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
                SecurityAudit::record('kyc.submitted', 'user', $user->id, [], $user->id);
                if ($oldPath) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return redirect()->route('kyc.form')->with('success', 'Your KYC has been submitted for review. Widget creation unlocks after approval.');
    }

    public function document(MerchantKyc $kyc)
    {
        abort_unless(Auth::guard('internal')->user()?->hasPermission('kyc.review') && $kyc->document_path, 403);
        SecurityAudit::record('kyc.document_downloaded', 'kyc', $kyc->id);
        return Storage::disk('local')->download($kyc->document_path, 'merchant-identity.'.pathinfo($kyc->document_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, MerchantKyc $kyc)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000']]);
        $reviewer = Auth::guard('internal')->user();
        abort_unless($reviewer?->hasPermission('kyc.review'), 403);
        DB::transaction(function () use ($kyc, $data, $reviewer) {
            \App\Models\User::whereKey($kyc->user_id)->lockForUpdate()->firstOrFail();
            $kyc = MerchantKyc::whereKey($kyc->id)->lockForUpdate()->firstOrFail();
            abort_unless($kyc->status === 'pending', 409, 'This submission has already been reviewed.');
            $kyc->update([...$data, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            SecurityAudit::record('kyc.'.$data['status'], 'kyc', $kyc->id);
        });
        return redirect()->route('internal.kyc')->with('success', 'The merchant KYC review is saved.');
    }
}
