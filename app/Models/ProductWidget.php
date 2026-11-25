<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductWidget extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['show_stock_level' => true];

    protected function casts(): array
    {
        return ['photos' => 'array', 'offer_ends_at' => 'immutable_datetime', 'delivery_enabled' => 'boolean', 'pickup_enabled' => 'boolean', 'delivery_areas' => 'array', 'stock_quantity' => 'integer', 'stock_version' => 'integer', 'show_stock_level' => 'boolean'];
    }

    public function currentPrice(): int
    {
        return $this->offer_minor !== null
            && ($this->offer_ends_at === null || $this->offer_ends_at->isFuture())
            ? min($this->price_minor, $this->offer_minor) : $this->price_minor;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function presentation(): array
    {
        return [
            'name' => $this->name,
            'stock' => $this->show_stock_level ? $this->stock_quantity : null,
            'inStock' => $this->stock_quantity > 0,
            'description' => $this->description,
            'price' => $this->price_minor / 100,
            'currentPrice' => $this->currentPrice() / 100,
            'offerEndsAt' => $this->offer_ends_at?->toIso8601String(),
            'photos' => array_map(fn ($i) => route('products.photo', [$this, $i]), array_keys($this->photos)),
            'url' => route('products.show', $this),
            'embedUrl' => route('products.embed', $this),
            'buyUrl' => route('products.checkout', $this),
        ];
    }
}
