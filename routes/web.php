<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\StorefrontController;
use Backpack\CRUD\app\Http\Controllers\Auth\ForgotPasswordController;
use Backpack\CRUD\app\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/shops/{shop:slug}', [StorefrontController::class, 'show'])->name('shops.show');
Route::post('/shops/{shop:slug}/cart', [StorefrontController::class, 'addToCart'])->middleware('throttle:60,1')->name('cart.add');
Route::get('/shops/{shop:slug}/cart', [StorefrontController::class, 'cartPage'])->name('cart.show');
Route::put('/shops/{shop:slug}/cart', [StorefrontController::class, 'updateCart'])->middleware('throttle:60,1')->name('cart.update');
Route::get('/shops/{shop:slug}/checkout', [StorefrontController::class, 'checkoutPage'])->name('checkout.show');
Route::post('/shops/{shop:slug}/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/payments/return', [CheckoutController::class, 'callback'])->middleware('throttle:30,1')->name('checkout.callback');
Route::post('/payments/paystack/{shop}/webhook', [CheckoutController::class, 'webhook'])->name('checkout.webhook');
Route::prefix(config('backpack.base.route_prefix', 'admin'))->group(function () {
    Route::get('login', [AuthController::class, 'loginForm'])->name('backpack.auth.login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('register', [AuthController::class, 'registerForm'])->name('backpack.auth.register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('logout', fn () => view('auth.logout'))->name('backpack.auth.logout');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('google.callback');
    Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('backpack.auth.password.reset');
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('backpack.auth.password.email');
    Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('backpack.auth.password.reset.token');
    Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1');
});
