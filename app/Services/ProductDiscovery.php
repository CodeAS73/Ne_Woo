<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductWidget;
use Illuminate\Database\Eloquent\Builder;

class ProductDiscovery
{
    private const CATEGORY_LIMIT = 6;

    public function picks(): array
    {
        $now = now()->toImmutable()->utc();
        $available = ProductWidget::query()->where('stock_quantity', '>', 0);
        $sales = Order::query()->confirmedPayments()
            ->where('payment.confirmed_at', '<=', $now)
            ->select('orders.product_widget_id')
            ->selectRaw('SUM(orders.quantity) as units')
            ->groupBy('orders.product_widget_id');

        $popular = $this->rankedBySales(clone $available, clone $sales);
        $trending = $this->rankedBySales(clone $available, $sales
            ->where('payment.confirmed_at', '>=', $now->subDays(7)));
        $offers = (clone $available)
            ->whereColumn('offer_minor', '<', 'price_minor')
            ->where('offer_minor', '>', 0)
            ->where(fn (Builder $query) => $query->whereNull('offer_ends_at')->orWhere('offer_ends_at', '>', $now))
            ->orderByRaw('CASE WHEN offer_ends_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('offer_ends_at')->latest('id')->limit(self::CATEGORY_LIMIT)->get();

        $cards = [];
        $categories = ['Most popular' => $popular, 'Trending now' => $trending, 'Offer' => $offers];
        for ($index = 0; $index < self::CATEGORY_LIMIT; $index++) {
            foreach ($categories as $label => $products) {
                $product = $products->get($index);
                if ($product === null) {
                    continue;
                }

                $cards[$product->id] ??= ['product' => $product, 'labels' => []];
                $cards[$product->id]['labels'][] = $label;
            }
        }

        return array_values($cards);
    }

    private function rankedBySales(Builder $products, Builder $sales)
    {
        return $products->joinSub($sales, 'sales', 'sales.product_widget_id', '=', 'product_widgets.id')
            ->select('product_widgets.*')->orderByDesc('sales.units')
            ->orderByDesc('product_widgets.id')->limit(self::CATEGORY_LIMIT)->get();
    }
}
