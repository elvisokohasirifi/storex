<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;

test('sales receipt page can download and share a PDF receipt on WhatsApp', function () {
    $shop = Shop::factory()->approved()->create();
    $order = Order::factory()->for($shop)->create(['status' => 'paid', 'customer_name' => 'Receipt Customer', 'customer_phone' => '+233241234567', 'total' => 2500, 'paid_at' => now()]);
    $product = Product::factory()->for($shop)->create();
    $order->items()->create(['product_id' => $product->id, 'name' => 'Bread', 'quantity' => 2, 'unit_price' => 1250, 'unit_cost' => 771, 'tracks_stock' => false]);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.receipt', $order))
        ->assertOk()
        ->assertSee('Download receipt')
        ->assertSee('Share with client on WhatsApp')
        ->assertSee('Back to till')
        ->assertSee(route('workspace.till', $shop), false)
        ->assertDontSee('Back to shop')
        ->assertSee(route('workspace.receipt.pdf', $order), false)
        ->assertSee('data-share-whatsapp', false)
        ->assertSee('application/pdf', false);

    $pdf = $this->get(route('workspace.receipt.pdf', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="receipt-'.$order->reference.'.pdf"');
    expect($pdf->getContent())->toStartWith('%PDF-1.4')
        ->toContain('Bread', 'Receipt Customer');
});

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
