<?php

use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ModerationController;
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
    Route::get('workspace/{shop}', [ShopWorkspaceController::class, 'show'])->name('workspace.show');
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
});
