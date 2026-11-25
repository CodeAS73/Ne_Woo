<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductWidget;
use App\Models\StockMovement;
use App\Services\AccountAccess;
use App\Services\StockInventory;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    public function edit(ProductWidget $product, StockInventory $inventory)
    {
        abort_unless(AccountAccess::manages($product), 404);
        $inventory->releaseExpired($product->id);
        $product->refresh();
        return view('merchant-stock', ['product' => $product, 'reserved' => Order::where('product_widget_id', $product->id)->where('stock_status', 'reserved')->sum('quantity'), 'movements' => StockMovement::where('product_widget_id', $product->id)->latest('id')->paginate(15)]);
    }

    public function update(Request $request, ProductWidget $product, StockInventory $inventory)
    {
        abort_unless(AccountAccess::manages($product), 404);
        $data = $request->validate(['stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'stock_version' => ['required', 'integer', 'min:0'], 'show_stock_level' => ['sometimes', 'boolean']]);
        $inventory->setAvailable($product, (int) $data['stock_quantity'], (int) $data['stock_version'], array_key_exists('show_stock_level', $data) ? (bool) $data['show_stock_level'] : null);
        return redirect()->route('merchant.stock', $product)->with('success', 'Available stock updated. Existing order reservations are unchanged.');
    }
}
