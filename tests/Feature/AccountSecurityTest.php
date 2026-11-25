<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\MerchantKyc;
use App\Models\ProductWidget;
use App\Models\Order;
use App\Services\UserInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, array $extra = []): User
    {
        $user = User::factory()->create(['password' => 'StrongPassword1234']);
        $user->forceFill(['role_id' => Role::where('name', $role)->sole()->id, 'account_type' => config('rbac.roles.'.$role.'.type'), 'is_active' => true, ...$extra])->save();
        return $user;
    }

    public function test_public_signup_cannot_grant_internal_access_or_self_approve_kyc(): void
    {
        $data = ['name' => 'New merchant', 'email' => 'merchant@example.test', 'password' => 'StrongPassword1234', 'password_confirmation' => 'StrongPassword1234', 'account_type' => 'merchant', 'role_id' => 1, 'is_active' => 1, 'kyc_status' => 'approved'];
        $this->post(route('register.store'), $data)->assertRedirect(route('kyc.form'));
        $user = User::where('email', $data['email'])->sole();
        $this->assertSame('merchant', $user->role->name);
        $this->assertFalse($user->canCreateWidgets());
        $this->post(route('products.store'), [])->assertForbidden();
        $this->get(route('internal.dashboard'))->assertRedirect(route('internal.login'));
        $this->post(route('register.store'), array_replace($data, ['email' => 'staff@example.test', 'account_type' => 'internal']))->assertSessionHasErrors('account_type');
    }

    public function test_guest_buyer_and_pending_merchant_cannot_create_widgets(): void
    {
        $this->post(route('products.store'), [])->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertSee('Sign up as a merchant')->assertDontSee('id="product-form"', false)->assertDontSee('/internal/login', false);
        $buyer = $this->account('buyer');
        $this->actingAs($buyer, 'web')->post(route('products.store'), [])->assertForbidden();
        $merchant = $this->account('merchant');
        $this->actingAs($merchant, 'web')->get(route('merchant.create'))->assertForbidden();
        MerchantKyc::create(['user_id' => $merchant->id, 'details' => [], 'status' => 'approved']);
        $this->actingAs($merchant->fresh(), 'web')->get(route('merchant.create'))->assertOk()->assertSee('id="product-form"', false);
    }

    public function test_public_and_internal_login_are_separate_and_staff_permissions_are_checked(): void
    {
        $admin = $this->account('system_admin');
        $buyer = $this->account('buyer');
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'StrongPassword1234'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->post(route('internal.login.store'), ['email' => $buyer->email, 'password' => 'StrongPassword1234'])->assertSessionHasErrors('email');
        $this->assertGuest('internal');
        $this->post(route('internal.login.store'), ['email' => $admin->email, 'password' => 'StrongPassword1234'])->assertRedirect(route('internal.dashboard'));
        $this->assertAuthenticatedAs($admin, 'internal');
        $this->assertGuest('web');
        $this->get(route('internal.users'))->assertOk();
    }

    public function test_support_cannot_create_users_review_kyc_or_view_private_documents(): void
    {
        Storage::fake('local');
        $merchant = $this->account('merchant');
        $kyc = MerchantKyc::create(['user_id' => $merchant->id, 'details' => ['id_number' => 'PRIVATE-123'], 'document_path' => 'kyc/private.pdf']);
        $support = $this->account('support');
        $this->actingAs($support, 'internal');
        $this->get(route('internal.users'))->assertOk()->assertDontSee('Create a profile');
        $this->post(route('internal.users.create'), ['role' => 'system_admin'])->assertForbidden();
        $this->get(route('internal.kyc'))->assertForbidden();
        $this->get(route('internal.kyc.document', $kyc))->assertForbidden();
        $this->post(route('internal.kyc.review', $kyc), ['status' => 'approved'])->assertForbidden();
    }

    public function test_kyc_is_encrypted_private_and_approval_unlocks_creation(): void
    {
        Storage::fake('local');
        $merchant = $this->account('merchant');
        $this->actingAs($merchant, 'web')->post(route('kyc.submit'), [
            'legal_name' => 'Demo Merchant', 'business_name' => 'Demo Shop', 'business_type' => 'individual',
            'id_type' => 'national_id', 'id_number' => 'PRIVATE-123', 'phone' => '0700000000',
            'business_address' => 'Private address', 'identity_document' => UploadedFile::fake()->image('id.png'), 'consent' => 1,
        ])->assertRedirect(route('kyc.form'));
        $kyc = MerchantKyc::sole();
        $this->assertSame('pending', $kyc->status);
        $this->assertStringNotContainsString('PRIVATE-123', DB::table('merchant_kyc')->value('details'));
        Storage::disk('local')->assertExists($kyc->document_path);
        $this->post(route('products.store'), [])->assertForbidden();
        $reviewer = $this->account('supervisor');
        $this->actingAs($reviewer, 'internal')->get(route('internal.kyc'))->assertOk()->assertSee('PRIVATE-123');
        $this->get(route('internal.dashboard'))->assertOk();
        $this->get(route('internal.kyc.document', $kyc))->assertOk();
        $this->post(route('internal.kyc.review', $kyc), ['status' => 'approved'])->assertRedirect();
        $this->assertTrue($merchant->fresh()->canCreateWidgets());
        $this->post(route('internal.kyc.review', $kyc), ['status' => 'rejected', 'review_note' => 'Again'])->assertConflict();
    }

    public function test_account_ownership_replaces_anonymous_widget_claims(): void
    {
        $owner = $this->account('merchant');
        $other = $this->account('merchant');
        $product = ProductWidget::create(['slug' => (string) Str::uuid(), 'name' => 'Bag', 'description' => 'Test', 'price_minor' => 10000, 'photos' => [], 'stock_quantity' => 100, 'merchant_user_id' => $owner->id]);
        $this->withSession(['created_widgets' => [$product->slug]]);
        $this->actingAs($other, 'web')->get(route('merchant.delivery', $product))->assertNotFound();
        $this->actingAs($owner, 'web')->get(route('merchant.delivery', $product))->assertOk();
        $admin = $this->account('system_admin');
        $this->actingAs($admin, 'internal')->get(route('merchant.delivery', $product))->assertOk();
        $this->get(route('internal.products'))->assertOk()->assertSee('Bag');
        $this->get(route('internal.audits'))->assertOk();
    }

    public function test_admin_creates_profiles_with_one_time_expiring_activation_and_no_shared_password(): void
    {
        $admin = $this->account('system_admin');
        $response = $this->actingAs($admin, 'internal')->post(route('internal.users.create'), ['name' => 'New Supervisor', 'email' => 'supervisor@example.test', 'role' => 'supervisor']);
        $response->assertSessionHas('activation_url');
        $url = session('activation_url');
        $token = basename($url);
        $user = User::where('email', 'supervisor@example.test')->sole();
        $this->assertFalse($user->is_active);
        $this->get($url)->assertOk()->assertSee('Set your password');
        $this->post(route('account.activate.store', $token), ['password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'])->assertRedirect(route('internal.login'));
        $this->assertTrue($user->fresh()->is_active);
        $this->post(route('account.activate.store', $token), ['password' => 'OtherPassword12345', 'password_confirmation' => 'OtherPassword12345'])->assertNotFound();
    }

    public function test_last_admin_cannot_be_disabled_or_demoted_and_access_changes_revoke_sessions(): void
    {
        $admin = $this->account('system_admin');
        $this->actingAs($admin, 'internal')->put(route('internal.users.update', $admin), ['role' => 'support', 'is_active' => 0])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isSystemAdmin());
        $support = $this->account('support');
        $this->put(route('internal.users.update', $support), ['role' => 'support', 'is_active' => 0])->assertRedirect();
        $this->actingAs($support->fresh(), 'internal')->get(route('internal.dashboard'))->assertRedirect(route('internal.login'));
    }

    public function test_login_is_rate_limited_and_logout_invalidates_session(): void
    {
        $buyer = $this->account('buyer');
        foreach (range(1, 5) as $ignored) {
            $this->post(route('login.store'), ['email' => $buyer->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), ['email' => $buyer->email, 'password' => 'StrongPassword1234'])->assertSessionHasErrors('email');
        $this->actingAs($buyer, 'web')->post(route('logout'))->assertRedirect(route('creator'));
        $this->assertGuest('web');
    }

    public function test_buyer_registration_skips_kyc_and_cannot_open_merchant_kyc(): void
    {
        $this->post(route('register.store'), ['name' => 'Buyer', 'email' => 'buyer@example.test', 'account_type' => 'buyer', 'password' => 'StrongPassword1234', 'password_confirmation' => 'StrongPassword1234'])->assertRedirect(route('account.dashboard'));
        $user = User::where('email', 'buyer@example.test')->sole();
        $this->assertSame('buyer', $user->role->name);
        $this->assertDatabaseCount('merchant_kyc', 0);
        $this->get(route('kyc.form'))->assertForbidden();
        $this->get(route('account.dashboard'))->assertOk()->assertSee('Your purchases');
    }

    public function test_expired_and_revoked_invitations_cannot_activate_users(): void
    {
        $admin = $this->account('system_admin');
        $user = $this->account('support', ['is_active' => false]);
        $url = UserInvitations::create($user);
        $this->travel(25)->hours();
        $this->get($url)->assertNotFound();
        $this->travelBack();
        $url = UserInvitations::create($user);
        $this->actingAs($admin, 'internal')->put(route('internal.users.update', $user), ['role' => 'support', 'is_active' => 0])->assertRedirect();
        $this->get($url)->assertNotFound();
    }

    public function test_session_version_change_ends_an_existing_authenticated_session(): void
    {
        $buyer = $this->account('buyer');
        $this->post(route('login.store'), ['email' => $buyer->email, 'password' => 'StrongPassword1234'])->assertRedirect();
        $this->assertAuthenticatedAs($buyer, 'web');
        $buyer->increment('session_version');
        // Simulate a fresh request's provider reload after an administrator changes access.
        $this->actingAs($buyer->fresh(), 'web')->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_guest_checkout_remains_available_and_account_orders_follow_the_account(): void
    {
        $product = ProductWidget::create(['slug' => (string) Str::uuid(), 'name' => 'Bag', 'description' => 'Bag', 'price_minor' => 10000, 'photos' => [], 'stock_quantity' => 100]);
        $this->get(route('products.checkout', $product))->assertOk()->assertSee('Guest checkout is welcome.');
        $buyer = $this->account('buyer');
        $this->actingAs($buyer, 'web')->post(route('checkout.quote', $product), ['quantity' => 1, 'full_name' => 'Buyer', 'phone' => '0700000000', 'notification_channel' => 'sms', 'fulfillment_method' => 'pickup'])->assertRedirect();
        $quote = \App\Models\CheckoutQuote::sole();
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertRedirect();
        $order = Order::sole();
        $this->assertSame($buyer->id, $order->buyer_user_id);
        $this->withSession(['checkout_access' => 'another-device']);
        $this->get(route('orders.show', $order))->assertOk();
        $other = $this->account('buyer');
        $this->actingAs($other, 'web')->get(route('orders.show', $order))->assertNotFound();
        $admin = $this->account('system_admin');
        $this->actingAs($admin, 'internal')->get(route('internal.orders'))->assertOk();
        $this->get(route('internal.orders.detail', $order))->assertOk()->assertSee('Buyer platform fee')->assertSee('Merchant platform fee');
        $support = $this->account('support');
        $this->actingAs($support, 'internal')->get(route('internal.orders.detail', $order))->assertForbidden();
    }
}
