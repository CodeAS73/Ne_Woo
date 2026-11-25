<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSession
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['web', 'internal'] as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user && (! $user->is_active || ($request->session()->has('auth_version.'.$guard) && (int) $request->session()->get('auth_version.'.$guard) !== $user->session_version))) {
                Auth::guard($guard)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route($guard === 'internal' ? 'internal.login' : 'login')->withErrors(['email' => 'Your session has ended. Please sign in again.']);
            }
        }
        $response = $next($request);
        if (Auth::guard('web')->check() || Auth::guard('internal')->check() || $request->is('internal*', 'account*', 'merchant*', 'login', 'register')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}
