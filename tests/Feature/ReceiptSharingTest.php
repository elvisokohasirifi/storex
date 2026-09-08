<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;

test('PDF receipt template contains customer receipt details without internal financial data', function () {
    $shop = Shop::factory()->create(['name' => 'Corner Shop', 'paystack_secret_key' => 'sk_test_hidden']);
    $order = Order::factory()->for($shop)->create(['status' => 'paid', 'customer_name' => 'Receipt Customer', 'customer_phone' => '+233241234567', 'customer_email' => null, 'total' => 2500, 'paid_at' => now(), 'payment_secret' => 'sk_test_orderhidden']);
    $product = Product::factory()->for($shop)->create();
    $order->items()->create(['product_id' => $product->id, 'name' => 'Bread', 'quantity' => 2, 'unit_price' => 1250, 'unit_cost' => 771, 'tracks_stock' => false]);

    $html = view('admin.receipt-pdf', ['order' => $order->load('shop', 'items')])->render();

    expect($html)->toContain('Corner Shop', 'Receipt Customer', '+233241234567', 'Bread', 'GHS 25.00');
    expect($html)->not->toContain('sk_test_hidden', 'sk_test_orderhidden', '7.71');
});

test('PDF receipt template escapes user content and distinguishes payments requiring review', function () {
    $shop = Shop::factory()->create(['name' => '<script>bad()</script>']);
    $order = Order::factory()->for($shop)->create(['status' => 'paid_review']);

    $html = view('admin.receipt-pdf', ['order' => $order->load('shop', 'items')])->render();

    expect($html)->toContain('&lt;script&gt;bad()&lt;/script&gt;', 'Payment received - review required');
    expect($html)->not->toContain('<script>bad()</script>');
});
