<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Order;
use App\Models\CheckoutQuote;
use App\Models\ProductWidget;
use App\Services\CheckoutAccess;
use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function registerForm(Request $request)
    {
        $this->rememberCheckout($request);
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => \App\Services\AccountPassword::rules(),
            'account_type' => ['required', Rule::in(['buyer', 'merchant'])],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->account_type = $data['account_type'];
            $user->role_id = Role::where('name', $data['account_type'])->sole()->id;
            $user->is_active = true;
            $user->save();
            SecurityAudit::record('account.registered', 'user', $user->id, ['account_type' => $user->account_type], $user->id);
            return $user;
        });
        $this->signIn($request, $user, 'web');
        if ($user->account_type === 'buyer' && ($url = $request->session()->pull('checkout_return'))) {
            return redirect($url);
        }
        return redirect()->route($user->account_type === 'merchant' ? 'kyc.form' : 'account.dashboard');
    }

    public function loginForm(Request $request)
    {
        if (! $request->routeIs('internal.*')) {
            $this->rememberCheckout($request);
        }
        return view('auth.login', ['internal' => $request->routeIs('internal.*')]);
    }

    public function login(Request $request)
    {
        $internal = $request->routeIs('internal.*');
        $guard = $internal ? 'internal' : 'web';
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string', 'max:1024']]);
        $key = 'login:'.$guard.':'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Please try again in a minute.']);
        }
        $user = User::where('email', $data['email'])->first();
        // Hash::check is also performed for missing accounts to reduce account enumeration.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid || ! $user->is_active || ($internal ? ! $user->hasPermission('internal.access') : $user->account_type === 'internal')) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The sign-in details could not be verified.']);
        }
        RateLimiter::clear($key);
        $this->signIn($request, $user, $guard);
        SecurityAudit::record('account.login', 'user', $user->id, ['portal' => $guard], $user->id);
        if (! $internal && ($url = $request->session()->pull('checkout_return'))) {
            return redirect($url);
        }
        return redirect()->route($internal ? 'internal.dashboard' : 'account.dashboard');
    }

    public function logout(Request $request)
    {
        $internal = $request->routeIs('internal.*');
        Auth::guard('web')->logout();
        Auth::guard('internal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route($internal ? 'internal.login' : 'creator');
    }

    public function dashboard(Request $request)
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && $user->is_active && $user->account_type !== 'internal', 403);
        $products = $user->account_type === 'merchant' ? ProductWidget::where('merchant_user_id', $user->id)->latest()->paginate(12) : null;
        $orders = Order::where('buyer_user_id', $user->id)->latest()->get(['reference', 'product_name', 'quantity', 'total_minor', 'payment_status']);
        return view('auth.dashboard', compact('user', 'products', 'orders'));
    }

    private function signIn(Request $request, User $user, string $guard): void
    {
        Auth::guard($guard === 'web' ? 'internal' : 'web')->logout();
        Auth::guard($guard)->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version.'.$guard, $user->session_version);
        if ($guard === 'web') {
            $hash = CheckoutAccess::hash($request);
            Order::whereNull('buyer_user_id')->where('buyer_hash', $hash)->update(['buyer_user_id' => $user->id]);
            CheckoutQuote::whereNull('buyer_user_id')->where('buyer_hash', $hash)->update(['buyer_user_id' => $user->id]);
        }
    }

    private function rememberCheckout(Request $request): void
    {
        if ($request->filled('checkout') && is_string($request->input('checkout'))) {
            $product = ProductWidget::where('slug', $request->input('checkout'))->first();
            if ($product) {
                $request->session()->put('checkout_return', route('products.checkout', $product));
            }
        }
    }
}
