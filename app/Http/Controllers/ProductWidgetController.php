<?php

namespace App\Http\Controllers;

use App\Models\ProductWidget;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\CheckoutAccess;
use App\Services\ProductDeliverySettings;
use App\Services\ProductDiscovery;
use App\Services\AccountAccess;

class ProductWidgetController extends Controller
{
    public function create(Request $request, ProductDiscovery $discovery)
    {
        $created = $request->filled('created') ? ProductWidget::where('slug', $request->query('created'))->first() : null;
        $created = $created && AccountAccess::manages($created) ? $created : null;

        return view('creator', [
            'created' => $created?->presentation(),
            'merchantOrdersUrl' => $created ? route('merchant.orders', $created) : null,
            'canCreate' => AccountAccess::user()?->canCreateWidgets() ?? false,
            'discoveryCards' => $request->routeIs('creator') ? $discovery->picks() : [],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(AccountAccess::user()?->canCreateWidgets(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:600'],
            'price' => ['required', 'numeric', 'min:1', 'max:99999999', 'decimal:0,2'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'show_stock_level' => ['sometimes', 'boolean'],
            'offer' => ['nullable', 'numeric', 'min:0.01', 'lt:price', 'decimal:0,2'],
            'offer_ends_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ]);
        $deliverySettings = app(ProductDeliverySettings::class)->validate($request);
        $endsAt = ! empty($data['offer_ends_at'])
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['offer_ends_at'], 'Africa/Nairobi')->utc() : null;
        if ($endsAt && (empty($data['offer']) || ! $endsAt->isFuture())) {
            throw ValidationException::withMessages(['offer_ends_at' => 'Add an offer price and choose a future end time (East Africa Time).']);
        }
        $paths = [];
        try {
            foreach ($request->file('photos') as $photo) {
                $paths[] = $photo->store('products', 'local');
            }
            $widget = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $endsAt, $paths, $deliverySettings, $request) {
            $widget = ProductWidget::create([
                'slug' => (string) Str::uuid(),
                'name' => $data['name'],
                'description' => $data['description'],
                'price_minor' => $this->minorUnits((string) $data['price']),
                'stock_quantity' => (int) $data['stock_quantity'],
                'show_stock_level' => $request->boolean('show_stock_level', true),
                'offer_minor' => isset($data['offer']) ? $this->minorUnits((string) $data['offer']) : null,
                'offer_ends_at' => $endsAt,
                'photos' => $paths,
                'merchant_user_id' => AccountAccess::user()->id,
                ...$deliverySettings,
            ]);
            app(\App\Services\StockInventory::class)->record($widget, 'initial_stock', 0);
            return $widget;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        $request->session()->push('created_widgets', $widget->slug);

        return redirect()->route('creator', ['created' => $widget->slug])->with('success', 'Your product is saved. Your widget, button and product link are ready to share.');
    }

    public function show(Request $request, ProductWidget $product)
    {
        app(\App\Services\StockInventory::class)->releaseExpired($product->id);
        $product->refresh();
        $owns = AccountAccess::manages($product);
        return response()->view('product', ['product' => $product->presentation(), 'embedded' => false, 'merchantShareUrl' => $owns ? route('merchant.share', $product) : null, 'merchantStockUrl' => $owns ? route('merchant.stock', $product) : null, 'merchantDeliveryUrl' => $owns ? route('merchant.delivery', $product) : null, 'merchantOrdersUrl' => $owns ? route('merchant.orders', $product) : null])->header('Cache-Control', 'private, no-store');
    }

    public function embed(ProductWidget $product)
    {
        app(\App\Services\StockInventory::class)->releaseExpired($product->id);
        $product->refresh();
        return response()->view('product', ['product' => $product->presentation(), 'embedded' => true])
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    public function photo(ProductWidget $product, int $index)
    {
        abort_unless(isset($product->photos[$index]), 404);
        return Storage::disk('local')->response($product->photos[$index], headers: ['X-Content-Type-Options' => 'nosniff']);
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
