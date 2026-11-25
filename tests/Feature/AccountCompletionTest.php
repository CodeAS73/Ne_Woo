<?php

namespace Tests\Feature;

use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\ProductWidget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_requires_confirmation_and_cannot_be_replayed(): void
    {
        $user = User::factory()->unverified()->create();
        $this->activateBuyer($user);
        $link = $this->expectEmail();
        $this->actingAs($user, 'web')->post(route('completion.send', 'verify-email'))->assertRedirect();
        $this->get($link())->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->post($link())->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->post($link())->assertGone();
    }

    public function test_reset_changes_password_and_revokes_existing_sessions(): void
    {
        $user = User::factory()->create();
        $this->activateBuyer($user);
        $version = $user->session_version;
        $link = $this->expectEmail();
        $this->post(route('completion.send', 'reset-password'), ['email' => $user->email])->assertRedirect();
        $data = ['password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'];
        $this->post($link(), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertSame($version + 1, $user->fresh()->session_version);
        $this->post($link(), $data)->assertGone();
    }

    public function test_guest_can_recover_an_order_from_another_browser(): void
    {
        $product = ProductWidget::create([
            'slug' => (string) Str::uuid(), 'name' => 'Bag', 'description' => 'Bag',
            'price_minor' => 10000, 'photos' => [], 'stock_quantity' => 100,
        ]);
        $email = 'guest@example.test';
        $this->post(route('checkout.quote', $product), [
            'quantity' => 1, 'full_name' => 'Guest', 'phone' => '0700000000',
            'email' => $email, 'notification_channel' => 'email', 'fulfillment_method' => 'pickup',
        ])->assertRedirect();
        $this->post(route('orders.place', CheckoutQuote::sole()), ['consent' => 1])->assertRedirect();
        $order = Order::sole();
        $orderPage = route('orders.show', $order);
        $this->withSession(['checkout_access' => 'different-browser'])->get($orderPage)->assertNotFound();
        $link = $this->expectEmail();
        $this->post(route('completion.send', 'recover-order'), [
            'reference' => $order->reference, 'email' => $email,
        ])->assertRedirect();
        $this->post($link())->assertRedirect($orderPage);
        $this->get($orderPage)->assertOk();
        $this->assertGuest('web');
        $this->assertNull($order->fresh()->buyer_user_id);
        $this->post($link())->assertGone();
        $this->travel(61)->minutes();
        $this->get($orderPage)->assertNotFound();
    }

    private function activateBuyer(User $user): void
    {
        $user->forceFill([
            'role_id' => Role::where('name', 'buyer')->sole()->id,
            'account_type' => 'buyer', 'is_active' => true, 'session_version' => 1,
        ])->save();
    }

    private function expectEmail(): \Closure
    {
        $url = null;
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $configure) use (&$url) {
            $url = trim(substr($body, strrpos($body, "\n") + 1));

            return is_callable($configure);
        });

        return function () use (&$url): string {
            $this->assertNotNull($url);

            return $url;
        };
    }
}
