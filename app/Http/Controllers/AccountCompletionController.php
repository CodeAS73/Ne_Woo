<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\AccountPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

class AccountCompletionController extends Controller
{
    private const TITLES = [
        'verify-email' => 'Verify your email',
        'reset-password' => 'Reset your password',
        'recover-order' => 'Recover a guest order',
    ];

    public function form(Request $request, string $purpose, ?string $token = null)
    {
        return response()->view('auth.completion', [
            'purpose' => $purpose, 'token' => $token, 'title' => self::TITLES[$purpose],
            'internal' => $request->routeIs('internal.completion.*'),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function send(Request $request, string $purpose)
    {
        $internal = $request->routeIs('internal.completion.*');
        $verification = $purpose === 'verify-email';
        $orderRecovery = $purpose === 'recover-order';
        $user = $verification ? Auth::guard($internal ? 'internal' : 'web')->user() : null;
        abort_if($verification && (! $user || ! $user->is_active), 403);

        $data = $verification ? ['email' => $user->email] : $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'reference' => $orderRecovery ? ['required', 'string', 'max:100'] : ['prohibited'],
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'completion:'.hash('sha256', $purpose.'|'.$email);
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 900);
            if ($orderRecovery) {
                $order = Order::where('reference', $data['reference'])->whereNull('buyer_user_id')->first();
                if ($order && hash_equals($email, strtolower(trim($order->buyer_details['email'] ?? '')))) {
                    $this->deliver($purpose, $order->id, $email, false);
                }
            } else {
                $user ??= User::where('email', $email)->first();
                if ($user && $user->is_active && ($user->account_type === 'internal') === $internal
                    && (! $verification || ! $user->email_verified_at)) {
                    $this->deliver($purpose, $user->id, $user->email, $internal);
                }
            }
        }

        return redirect()->route($internal ? 'internal.completion.form' : 'completion.form', $purpose)->with('success', 'If the details are eligible, an email with the next step will be sent.');
    }

    private function deliver(string $purpose, int $id, string $email, bool $internal): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        DB::table('account_completion_links')->upsert([[
            'purpose' => $purpose, 'subject_id' => $id, 'token_hash' => $hash,
            'email_hash' => $this->emailHash($email), 'expires_at' => now()->addMinutes(30),
        ]], ['purpose', 'subject_id'], ['token_hash', 'email_hash', 'expires_at']);
        $route = $internal ? 'internal.completion.confirm' : 'completion.confirm';
        $url = rtrim(config('app.url'), '/').route($route, compact('purpose', 'token'), false);
        try {
            Mail::raw("Open this link within 30 minutes to continue:\n\n".$url, function ($message) use ($email, $purpose) {
                $message->to($email)->subject(self::TITLES[$purpose]);
            });
        } catch (Throwable) {
            DB::table('account_completion_links')->where('token_hash', $hash)->delete();
            report(new RuntimeException('Ne_Woo could not send an account completion email.'));
        }
    }

    public function redeem(Request $request, string $purpose, string $token)
    {
        $internal = $request->routeIs('internal.completion.*');
        $password = $purpose === 'reset-password'
            ? $request->validate(['password' => AccountPassword::rules()])['password']
            : null;
        $subject = DB::transaction(function () use ($purpose, $token, $internal, $password) {
            $query = DB::table('account_completion_links')
                ->where('purpose', $purpose)->where('token_hash', hash('sha256', $token))
                ->where('expires_at', '>', now());
            $link = $query->lockForUpdate()->first();
            abort_unless($link, 410, 'This link has expired or has already been used.');
            if ($purpose === 'recover-order') {
                $order = Order::whereKey($link->subject_id)->lockForUpdate()->first();
                abort_unless($order && $order->buyer_user_id === null
                    && hash_equals($link->email_hash, $this->emailHash($order->buyer_details['email'] ?? '')), 410);
                $query->delete();

                return $order;
            }

            $user = User::whereKey($link->subject_id)->lockForUpdate()->first();
            abort_unless($user && $user->is_active && ($user->account_type === 'internal') === $internal
                && hash_equals($link->email_hash, $this->emailHash($user->email)), 410);
            if ($purpose === 'verify-email') {
                $user->email_verified_at ??= now();
            } else {
                $user->password = $password;
                $user->session_version++;
                $user->setRememberToken(null);
            }
            $user->save();
            $query->delete();

            return $user;
        }, 3);

        if ($subject instanceof Order) {
            $request->session()->regenerate();
            $request->session()->put('recovered_order.'.$subject->id, now()->addHour()->timestamp);

            return redirect()->route('orders.show', $subject);
        }

        $guard = $internal ? 'internal' : 'web';
        if ($password !== null && Auth::guard($guard)->id() === $subject->id) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route($internal ? 'internal.login' : 'login')
            ->with('success', $purpose === 'verify-email' ? 'Email verified.' : 'Password updated. Please sign in.');
    }

    private function emailHash(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), config('app.key'));
    }
}
