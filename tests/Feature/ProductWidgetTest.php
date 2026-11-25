<?php

namespace Tests\Feature;

use App\Models\ProductWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\MerchantKyc;

class ProductWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $merchant = User::factory()->create();
        $merchant->forceFill(['account_type' => 'merchant', 'role_id' => Role::where('name', 'merchant')->sole()->id, 'is_active' => true])->save();
        MerchantKyc::create(['user_id' => $merchant->id, 'details' => [], 'status' => 'approved']);
        $this->actingAs($merchant, 'web');
    }

    private function payload(int $photos = 1): array
    {
        return [
            'name' => 'Canvas bag', 'stock_quantity' => 10,
            'description' => 'Made for everyday adventures.',
            'price' => '4600.99',
            'offer' => '3450.50',
            'offer_ends_at' => now()->addDay()->timezone('Africa/Nairobi')->format('Y-m-d\TH:i'),
            'photos' => array_map(fn () => UploadedFile::fake()->image('bag.jpg'), range(1, $photos)),
        ];
    }

    public function test_saves_five_photos_and_provides_working_product_embed_and_checkout(): void
    {
        Storage::fake('local');
        $response = $this->post(route('products.store'), $this->payload(5));
        $product = ProductWidget::firstOrFail();
        $response->assertRedirect(route('creator', ['created' => $product->slug]));
        $this->assertCount(5, $product->photos);
        $this->assertSame(460099, $product->price_minor);
        $this->assertSame(345050, $product->currentPrice());
        foreach ($product->photos as $photo) {
            Storage::disk('local')->assertExists($photo);
        }
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Your saved product');
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('products.embed', $product))->assertOk()->assertHeader('Content-Security-Policy', 'frame-ancestors *');
        $this->get(route('products.photo', [$product, 0]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('products.show',$product))->assertSee('property="og:image"',false)->assertSee('name="twitter:image"',false)->assertSee(route('products.photo',[$product,0]),false);
        $this->get(route('merchant.share',$product))->assertSee('Photo preview for your shared link')->assertSee('share-cover-photo');
        $this->get(route('products.photo', [$product, 5]))->assertNotFound();
        $this->get(route('products.checkout', [$product, 'quantity' => 2]))->assertOk()->assertSee('6,901.00');
    }

    public function test_rejects_too_many_photos_and_invalid_or_expired_discounts(): void
    {
        Storage::fake('local');
        $this->post(route('products.store'), $this->payload(6))->assertSessionHasErrors('photos');
        $this->post(route('products.store'), array_replace($this->payload(), ['offer' => '5000']))->assertSessionHasErrors('offer');
        $this->post(route('products.store'), array_replace($this->payload(), ['offer_ends_at' => '2020-01-01T12:00']))->assertSessionHasErrors('offer_ends_at');
        $this->post(route('products.store'), array_replace($this->payload(), ['photos' => [UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]]))->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('product_widgets', 0);
    }

    public function test_expired_offer_reverts_to_normal_price_on_checkout_and_rejects_bad_quantity(): void
    {
        $product = ProductWidget::create([
            'slug' => (string) Str::uuid(), 'name' => 'Bag', 'description' => 'Canvas bag',
            'price_minor' => 460000, 'offer_minor' => 345000, 'offer_ends_at' => now()->addMinute(), 'photos' => [], 'stock_quantity' => 100,
        ]);
        $this->assertSame(345000, $product->currentPrice());
        $this->travel(2)->minutes();
        $this->assertSame(460000, $product->currentPrice());
        $this->get(route('products.checkout', [$product, 'quantity' => 2]))->assertSee('9,200.00');
        $this->getJson(route('products.checkout', [$product, 'quantity' => 0]))->assertUnprocessable();
        $this->getJson(route('products.checkout', [$product, 'quantity' => 100]))->assertUnprocessable();
    }

    public function test_saved_product_has_owner_only_sharing_with_public_links_and_embed_code(): void
    {
        $product = ProductWidget::create(['slug'=>(string)Str::uuid(),'merchant_user_id'=>auth('web')->id(),'name'=>'Canvas bag','description'=>'Bag','price_minor'=>100000,'photos'=>[],'stock_quantity'=>10]);
        $this->get(route('account.dashboard'))->assertOk()->assertSee(route('merchant.share',$product), false)->assertSee('Share product');
        $this->get(route('products.show',$product))->assertOk()->assertSee('Share product');
        $this->get(route('merchant.share',$product))->assertOk()->assertSee('Copy product link')->assertSee('Copy widget code')->assertSee('Copy sale button code')->assertSee(route('products.embed',$product),false)->assertSee('WhatsApp')->assertSee('SMS')->assertSee('local preview link');
        $other=User::factory()->create();
        $other->forceFill(['role_id'=>Role::where('name','merchant')->sole()->id,'account_type'=>'merchant','is_active'=>true])->save();
        $this->actingAs($other,'web')->get(route('merchant.share',$product))->assertNotFound();
        $this->get(route('products.show',$product))->assertOk()->assertDontSee('Share product');
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $this->get(route('merchant.share',$product))->assertNotFound();
    }
}
