<?php

namespace Tests\Feature;

use App\Models\CheckoutQuote;
use App\Models\Order;
use App\Models\OrderWorkflowEvent;
use App\Models\PaymentCollection;
use App\Models\ProductWidget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_use_nairobi_payment_months_and_owner_scoped_real_amounts(): void
    {
        $buyer = $this->user('buyer');
        $merchant = $this->user('merchant');
        $current = $this->order($buyer, $merchant, 'Current reporting bag', '2026-11-30 21:00:00');
        $this->order($buyer, $merchant, 'Prior reporting bag', '2026-11-30 20:59:59');
        $this->order($buyer, $merchant, 'Simulated bag', '2026-12-01 10:00:00', true);
        $other = $this->order($this->user('buyer'), $this->user('merchant'), 'Other private bag', '2026-12-01 10:00:00');

        $this->actingAs($buyer, 'web')->get(route('account.reports', ['month' => '2026-12']))
            ->assertOk()->assertViewHas('reports', function (array $reports) use ($current) {
                return count($reports) === 1
                    && (int) $reports[0]['periods']->first()->amount_minor === $current->total_minor
                    && $reports[0]['periods']->pluck('period')->all() === ['2026-12', '2026-11'];
            });

        $this->get(route('account.transactions', 'purchase'))->assertOk()
            ->assertSee($current->reference)->assertDontSee($other->reference);
        $this->get(route('account.transactions', 'sale'))->assertForbidden();
        $this->get(route('account.settlements'))->assertForbidden();

        $this->actingAs($merchant, 'web')->get(route('account.reports', ['month' => '2026-12']))
            ->assertOk()->assertSee('Current reporting bag')->assertSee('Prior reporting bag')
            ->assertDontSee('Simulated bag')->assertDontSee('Other private bag');

        $this->get(route('account.transactions', 'sale'))->assertOk()
            ->assertSee($current->reference)->assertDontSee($other->reference)->assertDontSee('Buyer fee');
        $this->get(route('account.settlements'))->assertOk()->assertSee($current->reference)->assertDontSee($other->reference);

        OrderWorkflowEvent::create(['order_id' => $current->id, 'actor_type' => 'supervisor',
            'action' => 'supervisor_approved', 'visibility' => 'internal', 'note' => 'Private reviewer note']);
        $this->get(route('account.settlements', $current->reference))->assertOk()
            ->assertSee('Supervisor approved')->assertDontSee('Private reviewer note');
        $this->get(route('account.settlements', $other->reference))->assertNotFound();
    }

    public function test_admin_can_grant_and_revoke_each_reporting_permission(): void
    {
        $supervisor = $this->user('supervisor');
        $admin = $this->user('system_admin');
        $this->actingAs($supervisor, 'internal');
        foreach (['reports', 'transactions', 'settlements'] as $page) {
            $this->get(route('internal.'.$page))->assertForbidden();
        }
        $this->put(route('internal.users.update', $supervisor), ['role' => 'supervisor', 'is_active' => 1])->assertForbidden();

        $this->actingAs($admin, 'internal');
        foreach (['reports', 'transactions', 'settlements'] as $page) {
            $this->get(route('internal.'.$page))->assertOk();
        }
        $this->put(route('internal.users.update', $supervisor), [
            'role' => 'supervisor', 'is_active' => 1, 'reporting_permissions' => ['*'],
        ])->assertSessionHasErrors('reporting_permissions.0');
        $this->put(route('internal.users.update', $supervisor), [
            'role' => 'supervisor', 'is_active' => 1, 'reporting_permissions' => ['reports.transactions'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($supervisor->fresh(), 'internal');
        $this->get(route('internal.transactions'))->assertOk();
        $this->get(route('internal.reports'))->assertForbidden();
        $this->get(route('internal.settlements'))->assertForbidden();

        $this->actingAs($admin, 'internal')->put(route('internal.users.update', $supervisor), [
            'role' => 'supervisor', 'is_active' => 1, 'edit_reporting_permissions' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($supervisor->fresh(), 'internal')->get(route('internal.transactions'))->assertForbidden();
    }

    public function test_home_discovery_ranks_sales_and_only_shows_live_available_offers(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 10)->setTime(12, 0));
        $buyer = $this->user('buyer');
        $merchant = $this->user('merchant');
        $popular = $this->order($buyer, $merchant, 'Long-running bestseller', '2026-11-20 12:00:00');
        $popular->update(['quantity' => 20]);
        $trend = $this->order($buyer, $merchant, 'Recent bestseller', '2026-12-09 12:00:00');
        $this->order($buyer, $merchant, 'Simulated bestseller', '2026-12-09 12:00:00', true);

        $offer = ProductWidget::whereKey($trend->product_widget_id)->firstOrFail();
        $offer->update(['offer_minor' => 75000, 'offer_ends_at' => now()->addDay()]);
        foreach (['Expired discount', 'Sold-out discount'] as $name) {
            ProductWidget::create([
                'slug' => (string) Str::uuid(), 'name' => $name, 'description' => 'Fixture',
                'price_minor' => 100000, 'offer_minor' => 50000, 'photos' => [],
                'offer_ends_at' => $name === 'Expired discount' ? now()->subDay() : null,
                'stock_quantity' => $name === 'Sold-out discount' ? 0 : 5,
            ]);
        }

        $this->get(route('creator'))->assertOk()
            ->assertSeeInOrder(['Create your product widget', 'Discover products', 'Long-running bestseller', 'Recent bestseller'])
            ->assertSee(route('products.show', $offer))->assertSee('KES 750.00')
            ->assertDontSee('Simulated bestseller')->assertDontSee('Expired discount')->assertDontSee('Sold-out discount')
            ->assertViewHas('discoveryCards', function (array $cards) {
                return count($cards) === 2
                    && $cards[0]['labels'] === ['Most popular']
                    && $cards[1]['labels'] === ['Trending now', 'Offer', 'Most popular'];
            });
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->sole()->id,
            'account_type' => config('rbac.roles.'.$role.'.type'),
            'is_active' => true,
        ]);
    }

    private function order(User $buyer, User $merchant, string $name, string $paidAt, bool $test = false): Order
    {
        $product = ProductWidget::create([
            'slug' => (string) Str::uuid(), 'merchant_user_id' => $merchant->id,
            'name' => $name, 'description' => 'Reporting fixture', 'price_minor' => 100000,
            'photos' => [], 'stock_quantity' => 100, 'pickup_enabled' => true,
        ]);
        $this->actingAs($buyer, 'web')->post(route('checkout.quote', $product), [
            'quantity' => 2, 'full_name' => 'Report Buyer', 'phone' => '0700000000',
            'fulfillment_method' => 'pickup', 'notification_channel' => 'sms',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quote = CheckoutQuote::where('product_widget_id', $product->id)->sole();
        $this->post(route('orders.place', $quote), ['consent' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::where('product_widget_id', $product->id)->sole();
        $order->update(['payment_status' => $test ? 'test_paid' : 'paid']);
        PaymentCollection::where('order_id', $order->id)->update([
            'status' => $test ? 'test_confirmed' : 'confirmed', 'confirmed_at' => $paidAt,
            'provider_reference' => (string) Str::uuid(),
        ]);
        Auth::guard('web')->logout();

        return $order;
    }
}
