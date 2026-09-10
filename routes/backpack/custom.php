<?php

use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\ProductClassificationController;
use App\Http\Controllers\Admin\ShopWorkspaceController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge((array) config('backpack.base.web_middleware', 'web'), (array) config('backpack.base.middleware_key', 'admin')),
    'namespace' => 'App\\Http\\Controllers\\Admin',
], function () {
    Route::crud('shop', 'ShopCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::crud('order', 'OrderCrudController');
    Route::get('workspace', [ShopWorkspaceController::class, 'index'])->name('workspace.index');
    Route::get('inventory-management', [ShopWorkspaceController::class, 'inventoryManagement'])->name('inventory-management.index');
    Route::get('workspace/{shop}', [ShopWorkspaceController::class, 'show'])->name('workspace.show');
    Route::get('workspace/{shop}/operations', [ShopWorkspaceController::class, 'operations'])->name('workspace.operations');
    Route::post('workspace/{shop}/variants', [ShopWorkspaceController::class, 'variant'])->name('workspace.variant');
    Route::post('workspace/{shop}/transfers', [ShopWorkspaceController::class, 'transferStock'])->name('workspace.transfer');
    Route::post('workspace/{shop}/suppliers', [ShopWorkspaceController::class, 'supplier'])->name('workspace.supplier');
    Route::post('workspace/{shop}/purchase-orders', [ShopWorkspaceController::class, 'purchaseOrder'])->name('workspace.purchase-order');
    Route::post('workspace/{shop}/purchase-orders/{purchaseOrder}/receive', [ShopWorkspaceController::class, 'receivePurchaseOrder'])->name('workspace.purchase-order.receive');
    Route::post('workspace/{shop}/shifts/open', [ShopWorkspaceController::class, 'openShift'])->name('workspace.shift.open');
    Route::post('workspace/{shop}/shifts/{shift}/close', [ShopWorkspaceController::class, 'closeShift'])->name('workspace.shift.close');
    Route::post('workspace/{shop}/orders/{order}/cancel', [ShopWorkspaceController::class, 'cancelSale'])->name('workspace.sale.cancel');
    Route::post('workspace/{shop}/orders/{order}/refund', [ShopWorkspaceController::class, 'refundSale'])->name('workspace.sale.refund');
    Route::get('workspace/{shop}/exports/products', [ShopWorkspaceController::class, 'exportProducts'])->name('workspace.exports.products');
    Route::post('workspace/{shop}/bulk-prices', [ShopWorkspaceController::class, 'bulkPrices'])->name('workspace.bulk-prices');
    Route::get('workspace/{shop}/payments', [ShopWorkspaceController::class, 'payments'])->name('workspace.payments');
    Route::get('workspace/{shop}/users', [ShopWorkspaceController::class, 'users'])->name('workspace.users');
    Route::get('workspace/{shop}/till', [ShopWorkspaceController::class, 'till'])->name('workspace.till');
    Route::get('workspace/{shop}/inventory', [ShopWorkspaceController::class, 'inventory'])->name('workspace.inventory');
    Route::get('workspace/{shop}/inventory/{product}', [ShopWorkspaceController::class, 'stock'])->name('workspace.stock');
    Route::post('workspace/{shop}/inventory/receive', [ShopWorkspaceController::class, 'receiveStock'])->name('workspace.inventory.receive');
    Route::post('workspace/{shop}/inventory/adjust', [ShopWorkspaceController::class, 'adjustStock'])->name('workspace.inventory.adjust');
    Route::post('workspace/{shop}/inventory/return', [ShopWorkspaceController::class, 'returnStock'])->name('workspace.inventory.return');
    Route::post('products/bulk-entry', [ShopWorkspaceController::class, 'bulkProducts'])->name('products.bulk');
    Route::post('workspace/{shop}/credentials', [ShopWorkspaceController::class, 'credentials'])->name('workspace.credentials');
    Route::post('workspace/{shop}/members', [ShopWorkspaceController::class, 'member'])->name('workspace.member');
    Route::delete('workspace/{shop}/members/{member}', [ShopWorkspaceController::class, 'removeMember'])->name('workspace.member.remove');
    Route::post('workspace/{shop}/bulk', [ShopWorkspaceController::class, 'bulk'])->name('workspace.bulk');
    Route::post('workspace/{shop}/sale', [ShopWorkspaceController::class, 'sale'])->name('workspace.sale');
    Route::post('products/{product}/duplicate', [ShopWorkspaceController::class, 'duplicate'])->name('workspace.duplicate');
    Route::get('receipts/{order}', [ShopWorkspaceController::class, 'receipt'])->name('workspace.receipt');
    Route::get('moderation', [ModerationController::class, 'index'])->name('moderation.index');
    Route::post('moderation', [ModerationController::class, 'store'])->name('moderation.store');
    Route::crud('ledger-entry', 'LedgerEntryCrudController');
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/receipts/{entry}', [FinanceController::class, 'receipt'])->name('finance.receipt');
    Route::get('security', [AuthController::class, 'security'])->name('account.security');
    Route::post('security', [AuthController::class, 'updateSecurity'])->middleware('throttle:5,1')->name('account.security.update');
    Route::get('/', [ShopWorkspaceController::class, 'index'])->name('backpack');
    Route::get('dashboard', [ShopWorkspaceController::class, 'index'])->name('backpack.dashboard');
    Route::crud('product-category', 'ProductCategoryCrudController');
    Route::crud('brand', 'BrandCrudController');
    Route::crud('shop-discount', 'ShopDiscountCrudController');
    Route::post('product-classifications/category', [ProductClassificationController::class, 'category'])->name('product-classifications.category');
    Route::post('product-classifications/brand', [ProductClassificationController::class, 'brand'])->name('product-classifications.brand');
});
