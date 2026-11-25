<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class CheckoutAccess
{
    public static function hash(Request $request): string
    {
        if (! $request->session()->has('checkout_access')) {
            $request->session()->put('checkout_access', Str::random(64));
        }

        return hash('sha256', $request->session()->get('checkout_access'));
    }

    public static function owns(Request $request, $record): bool
    {
        $user = Auth::guard('web')->user();
        if ($record->buyer_user_id !== null) {
            return $user && $user->is_active && $user->id === $record->buyer_user_id;
        }
        return hash_equals($record->buyer_hash, self::hash($request));
    }
}
