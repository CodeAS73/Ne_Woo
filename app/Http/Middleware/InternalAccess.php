<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InternalAccess
{
    public function handle(Request $request, Closure $next, string $permission = 'internal.access')
    {
        $user = Auth::guard('internal')->user();
        if (! $user) {
            return $request->expectsJson() ? response()->json(['message' => 'Unauthenticated.'], 401) : redirect()->route('internal.login');
        }
        abort_unless($user->account_type === 'internal' && $user->hasPermission('internal.access') && $user->hasPermission($permission), 403);
        return $next($request);
    }
}
