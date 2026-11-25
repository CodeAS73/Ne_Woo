<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductWidget;
use App\Services\CheckoutAccess;
use App\Services\OrderPresenter;
use App\Services\ProductDeliverySettings;
use App\Services\AccountAccess;
use Illuminate\Http\Request;

class MerchantOrderController extends Controller
{
    public function index(Request $request, ProductWidget $product, OrderPresenter $presenter)
    {
        $this->authorizeCreator($request, $product);
        $query = Order::where('product_widget_id', $product->id);
        if (! AccountAccess::user()->isSystemAdmin()) {
            $query->where('merchant_user_id', AccountAccess::user()->id);
        }
        $orders = $query->latest()->paginate(15);
        $rows = $orders->getCollection()->map(fn ($order) => $presenter->merchant($order))->all();

        return response()->view('merchant-orders', ['productName' => $product->name, 'rows' => $rows, 'pagination' => $orders->links()])->header('Cache-Control', 'no-store, private');
    }

    public function delivery(Request $request, ProductWidget $product)
    {
        $this->authorizeCreator($request, $product);

        return response()->view('merchant-delivery', compact('product'))->header('Cache-Control', 'private, no-store');
    }

    public function updateDelivery(Request $request, ProductWidget $product, ProductDeliverySettings $settings)
    {
        $this->authorizeCreator($request, $product);
        $product->update($settings->validate($request));

        return redirect()->route('merchant.delivery', $product)->with('success', 'Your delivery and pickup options are saved. Existing orders keep their original agreed terms.');
    }

    private function authorizeCreator(Request $request, ProductWidget $product): void
    {
        abort_unless(AccountAccess::manages($product), 404);
    }

    public function all(Request $request, OrderPresenter $presenter)
    {
        $user = AccountAccess::user();
        abort_unless($user && ($user->hasPermission('orders.fulfill_own') || $user->isSystemAdmin()), 404);
        $query = Order::query();
        if (! $user->isSystemAdmin()) {
            $query->where('merchant_user_id', $user->id);
        }
        $orders = $query->latest()->paginate(15);
        return view('merchant-orders', ['productName' => 'Your merchant orders', 'rows' => $orders->getCollection()->map(fn ($order) => $presenter->merchant($order))->all(), 'pagination' => $orders->links()]);
    }
}
