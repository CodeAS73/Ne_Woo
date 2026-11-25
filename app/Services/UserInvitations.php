<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivation;
use Illuminate\Support\Str;

class UserInvitations
{
    public static function create(User $user): string
    {
        UserActivation::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        $token = Str::random(64);
        UserActivation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        return route('account.activate', $token);
    }
}
