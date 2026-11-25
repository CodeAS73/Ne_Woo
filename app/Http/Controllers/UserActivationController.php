<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivation;
use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserActivationController extends Controller
{
    private function invitation(string $token): UserActivation
    {
        return UserActivation::where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function form(string $token)
    {
        $invitation = $this->invitation($token);
        $user = User::findOrFail($invitation->user_id);
        return response()->view('auth.activate', ['token' => $token, 'email' => $user->email, 'internal' => $user->account_type === 'internal'])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function activate(Request $request, string $token)
    {
        $data = $request->validate(['password' => \App\Services\AccountPassword::rules()]);
        $internal = DB::transaction(function () use ($token, $data) {
            $invitation = $this->invitation($token);
            $invitation = UserActivation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($invitation->used_at || $invitation->expires_at->isPast(), 404);
            $user = User::whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            $user->password = $data['password'];
            $user->is_active = true;
            $user->session_version++;
            $user->save();
            $invitation->update(['used_at' => now()]);
            SecurityAudit::record('account.activated', 'user', $user->id, [], $user->id);
            return $user->account_type === 'internal';
        });
        return redirect()->route($internal ? 'internal.login' : 'login')->with('success', 'Your password is set. You can now sign in.');
    }
}
