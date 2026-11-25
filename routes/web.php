<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductWidgetController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\MerchantOrderController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\InternalController;
use App\Http\Controllers\UserActivationController;
use App\Http\Controllers\DeliveryWorkflowController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\ProductShareController;
use App\Http\Controllers\MerchantFeeController;
use App\Http\Controllers\BuyerFeeController;
use App\Http\Controllers\ReportingController;

Route::get('/internal/buyer-fees',[BuyerFeeController::class,'index'])->middleware('internal:buyer_fees.manage')->name('internal.buyer-fees');
Route::post('/internal/buyer-fees',[BuyerFeeController::class,'publish'])->middleware(['internal:buyer_fees.manage','throttle:15,1'])->name('internal.buyer-fees.publish');

Route::get('/internal/merchant-fees', [MerchantFeeController::class,'index'])->middleware('internal:fees.manage')->name('internal.merchant-fees');
Route::post('/internal/merchant-fees', [MerchantFeeController::class,'publish'])->middleware(['internal:fees.manage','throttle:15,1'])->name('internal.merchant-fees.publish');

Route::get('/merchant/products/{product}/share', [ProductShareController::class, 'show'])->name('merchant.share');

Route::get('/merchant/products/{product}/stock', [ProductStockController::class, 'edit'])->name('merchant.stock');
Route::put('/merchant/products/{product}/stock', [ProductStockController::class, 'update'])->middleware('throttle:20,1')->name('merchant.stock.update');

Route::post('/merchant/orders/{order}/fulfillment', [DeliveryWorkflowController::class, 'merchant'])->middleware('throttle:20,1')->name('orders.fulfill');
Route::post('/orders/{order}/confirm-receipt', [DeliveryWorkflowController::class, 'confirm'])->middleware('throttle:10,1')->name('orders.confirm-receipt');
Route::post('/orders/{order}/delivery-issue', [DeliveryWorkflowController::class, 'issue'])->middleware('throttle:10,1')->name('orders.delivery-issue');
Route::get('/internal/delivery-reviews', [DeliveryWorkflowController::class, 'queue'])->middleware('internal:settlements.review')->name('internal.delivery-reviews');
Route::post('/internal/delivery-reviews/{order}', [DeliveryWorkflowController::class, 'review'])->middleware(['internal:settlements.review', 'throttle:20,1'])->name('internal.delivery-review');

Route::get('/', [ProductWidgetController::class, 'create'])->name('creator');
Route::post('/products', [ProductWidgetController::class, 'store'])->middleware(['merchant.create', 'throttle:20,1'])->name('products.store');
Route::get('/merchant/widgets/create', [ProductWidgetController::class, 'create'])->middleware('merchant.create')->name('merchant.create');
Route::get('/register', [AccountController::class, 'registerForm'])->name('register');
Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:8,1')->name('register.store');
Route::get('/login', [AccountController::class, 'loginForm'])->name('login');
Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:15,1')->name('login.store');
Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
Route::get('/account', [AccountController::class, 'dashboard'])->middleware('auth:web')->name('account.dashboard');
Route::middleware('auth:web')->group(function () {
    Route::get('/account/reports', [ReportingController::class, 'dashboard'])->name('account.reports');
    Route::get('/account/transactions/{scope}/{reference?}', [ReportingController::class, 'ledger'])
        ->whereIn('scope', ['purchase', 'sale'])->name('account.transactions');
    Route::get('/account/settlements/{reference?}', [ReportingController::class, 'ledger'])
        ->defaults('scope', 'sale')->name('account.settlements');
});
Route::get('/internal/reports', [ReportingController::class, 'dashboard'])
    ->middleware('internal:reports.dashboard')->name('internal.reports');
Route::get('/internal/transactions/{reference?}', [ReportingController::class, 'ledger'])
    ->middleware('internal:reports.transactions')->name('internal.transactions');
Route::get('/internal/settlements/{reference?}', [ReportingController::class, 'ledger'])
    ->middleware('internal:reports.settlements')->name('internal.settlements');

Route::get('/merchant/kyc', [KycController::class, 'form'])->middleware('auth:web')->name('kyc.form');
Route::post('/merchant/kyc', [KycController::class, 'submit'])->middleware(['auth:web', 'throttle:5,1'])->name('kyc.submit');
Route::get('/account/activate/{token}', [UserActivationController::class, 'form'])->middleware('throttle:15,1')->name('account.activate');
Route::post('/account/activate/{token}', [UserActivationController::class, 'activate'])->middleware('throttle:8,1')->name('account.activate.store');

// Internal routes deliberately have no entry link in the public navigation.
Route::get('/internal/login', [AccountController::class, 'loginForm'])->name('internal.login');
Route::post('/internal/login', [AccountController::class, 'login'])->middleware('throttle:15,1')->name('internal.login.store');
Route::post('/internal/logout', [AccountController::class, 'logout'])->middleware('internal')->name('internal.logout');
Route::get('/internal', [InternalController::class, 'dashboard'])->middleware('internal')->name('internal.dashboard');
Route::get('/internal/users', [InternalController::class, 'users'])->middleware('internal:users.view')->name('internal.users');
Route::post('/internal/users', [InternalController::class, 'createUser'])->middleware(['internal:users.manage', 'throttle:10,1'])->name('internal.users.create');
Route::put('/internal/users/{user}', [InternalController::class, 'updateUser'])->middleware('internal:users.manage')->name('internal.users.update');
Route::post('/internal/users/{user}/invite', [InternalController::class, 'invite'])->middleware(['internal:users.manage', 'throttle:10,1'])->name('internal.users.invite');
Route::get('/internal/kyc', [InternalController::class, 'kyc'])->middleware('internal:kyc.review')->name('internal.kyc');
Route::get('/internal/kyc/{kyc}/document', [KycController::class, 'document'])->middleware('internal:kyc.review')->name('internal.kyc.document');
Route::post('/internal/kyc/{kyc}/review', [KycController::class, 'review'])->middleware('internal:kyc.review')->name('internal.kyc.review');
Route::get('/internal/products', [InternalController::class, 'products'])->middleware('internal:products.manage_all')->name('internal.products');
Route::put('/internal/products/{product}/owner', [InternalController::class, 'assignProduct'])->middleware('internal:products.manage_all')->name('internal.products.assign');
Route::get('/internal/orders', [InternalController::class, 'orders'])->middleware('internal:orders.view_all')->name('internal.orders');
Route::get('/internal/orders/{order}', [InternalController::class, 'orderDetail'])->middleware('internal:orders.finance')->name('internal.orders.detail');
Route::get('/internal/audits', [InternalController::class, 'audits'])->middleware('internal:audits.view')->name('internal.audits');
Route::get('/p/{product}', [ProductWidgetController::class, 'show'])->name('products.show');
Route::get('/p/{product}/photo/{index}', [ProductWidgetController::class, 'photo'])->whereNumber('index')->name('products.photo');
Route::get('/p/{product}/embed', [ProductWidgetController::class, 'embed'])->name('products.embed');
Route::get('/p/{product}/checkout', [CheckoutController::class, 'details'])->name('products.checkout');
Route::post('/p/{product}/checkout/review', [CheckoutController::class, 'quote'])->middleware('throttle:30,1')->name('checkout.quote');
Route::get('/checkout/review/{quote}', [CheckoutController::class, 'review'])->name('checkout.review');
Route::post('/checkout/review/{quote}/place', [CheckoutController::class, 'place'])->middleware('throttle:20,1')->name('orders.place');
Route::get('/orders/{order}', [CheckoutController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/payment', [OrderPaymentController::class, 'start'])->middleware('throttle:6,1')->name('orders.pay');
Route::post('/orders/{order}/payment/simulate', [OrderPaymentController::class, 'simulate'])->middleware('throttle:10,1')->name('orders.simulate');
Route::get('/merchant/products/{product}/orders', [MerchantOrderController::class, 'index'])->name('merchant.orders');
Route::get('/merchant/orders', [MerchantOrderController::class, 'all'])->name('merchant.orders.all');
Route::get('/merchant/products/{product}/delivery', [MerchantOrderController::class, 'delivery'])->name('merchant.delivery');
Route::put('/merchant/products/{product}/delivery', [MerchantOrderController::class, 'updateDelivery'])->middleware('throttle:20,1')->name('merchant.delivery.update');

Route::get('/orders/{order}/operations', [\App\Http\Controllers\OrderOperationsController::class, 'show'])->name('orders.operations');
Route::post('/orders/{order}/operations', [\App\Http\Controllers\OrderOperationsController::class, 'submit'])
    ->middleware('throttle:15,1,order-operations')->name('orders.operations.submit');
Route::middleware('internal:settlements.review')->group(function () {
    Route::get('/internal/order-cases', [\App\Http\Controllers\OrderOperationsController::class, 'queue'])->name('internal.order-cases');
    Route::get('/internal/order-cases/{order}', [\App\Http\Controllers\OrderOperationsController::class, 'show'])->name('internal.order-operations');
    Route::post('/internal/order-cases/{order}', [\App\Http\Controllers\OrderOperationsController::class, 'submit'])
        ->middleware('throttle:15,1,internal-order-operations')->name('internal.order-operations.submit');
});
