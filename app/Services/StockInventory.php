<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductWidget;
use App\Models\PaymentCollection;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockInventory
{
    public function assertAvailable(ProductWidget $product, int $quantity): void
    {
        if ($quantity < 1 || $quantity > $product->stock_quantity) {
            throw ValidationException::withMessages(['quantity' => $product->stock_quantity > 0 ? ($product->show_stock_level ? "Only {$product->stock_quantity} unit(s) are currently available. Please update your quantity." : 'The requested quantity is unavailable. Please choose a smaller quantity.') : 'This product is out of stock.']);
        }
    }

    // Caller must hold the product row lock inside the order placement transaction.
    public function reserve(ProductWidget $product, Order $order): void
    {
        $this->assertAvailable($product, $order->quantity);
        $before = $product->stock_quantity;
        $product->stock_quantity -= $order->quantity;
        $product->stock_version++;
        $product->save();
        $minutes = $order->unit_price_minor < $product->price_minor ? 5 : 30;
        $order->update(['stock_status' => 'reserved', 'stock_reserved_until' => now()->addMinutes($minutes)]);
        $this->record($product, 'reserved', $before, $order->id);
    }

    public function setAvailable(ProductWidget $product, int $quantity, int $expectedVersion, ?bool $showStock = null): void
    {
        DB::transaction(function () use ($product, $quantity, $expectedVersion, $showStock) {
            $product = ProductWidget::whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless(AccountAccess::manages($product), 404);
            if ($product->stock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['stock_quantity' => 'Stock changed while you were editing. Reload this page, check the latest quantity and try again.']);
            }
            $before = $product->stock_quantity;
            $product->update(['stock_quantity' => $quantity, 'stock_version' => $product->stock_version + 1, 'show_stock_level' => $showStock ?? $product->show_stock_level]);
            $this->record($product, 'merchant_adjustment', $before);
            SecurityAudit::record('product.stock_updated', 'product', $product->id, ['before' => $before, 'after' => $quantity]);
        });
    }

    public function expireOrder(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->stock_status !== 'reserved' || $order->payment_status !== 'unpaid' || ! $order->stock_reserved_until?->lte(now())) {
                return false;
            }
            $payment = PaymentCollection::where('order_id', $id)->lockForUpdate()->firstOrFail();
            // Never free inventory while a provider payment may still be in progress.
            if (! in_array($payment->status, ['not_initiated', 'test_failed'], true)) {
                return false;
            }
            $this->restore($order, $order->quantity, 'reservation_expired');
            $order->update(['stock_status' => 'released', 'status' => 'expired']);
            $payment->update(['status' => 'expired']);
            return true;
        });
    }

    // Caller holds the order lock and verifies the units are eligible for restocking.
    public function restore(Order $order, int $quantity, string $action): void
    {
        $product = ProductWidget::whereKey($order->product_widget_id)->lockForUpdate()->firstOrFail();
        $before = $product->stock_quantity;
        $product->stock_quantity += $quantity;
        $product->stock_version++;
        $product->save();
        $this->record($product, $action, $before, $order->id);
    }

    public function releaseExpired(?int $productId = null): int
    {
        $query = Order::where('stock_status', 'reserved')->where('payment_status', 'unpaid')->where('stock_reserved_until', '<=', now())
            ->whereIn('id', PaymentCollection::whereIn('status', ['not_initiated', 'test_failed'])->select('order_id'));
        if ($productId !== null) {
            $query->where('product_widget_id', $productId);
        }
        $count = 0;
        foreach ($query->limit(200)->pluck('id') as $id) {
            $count += (int) $this->expireOrder($id);
        }
        return $count;
    }

    public function assertPayable(Order $order): void
    {
        if ($order->stock_status === 'released' || in_array($order->status, ['expired', 'cancelled', 'refunded'], true)) {
            throw ValidationException::withMessages(['payment' => 'This order can no longer be paid. Please place a new order.']);
        }
    }

    public function record(ProductWidget $product, string $action, int $before, ?int $orderId = null): void
    {
        StockMovement::create(['product_widget_id' => $product->id, 'order_id' => $orderId, 'actor_id' => AccountAccess::user()?->id, 'action' => $action, 'before_quantity' => $before, 'after_quantity' => $product->stock_quantity]);
    }
}
