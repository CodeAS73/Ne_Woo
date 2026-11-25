<?php

namespace App\Http\Middleware;

use App\Services\AccountAccess;
use Closure;
use Illuminate\Http\Request;

class MerchantCreation
{
    public function handle(Request $request, Closure $next)
    {
        $user = AccountAccess::user();
        if (! $user) {
            return $request->expectsJson() ? response()->json(['message' => 'Sign in as a merchant.'], 401) : redirect()->route('login');
        }
        abort_unless($user->canCreateWidgets(), 403, 'Merchant KYC approval is required before creating a widget.');
        return $next($request);
    }
}
