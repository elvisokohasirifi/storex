<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('cash sales require a phone and accept an omitted email', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.sale', $shop), [
        'customer_name' => 'Phone customer', 'customer_phone' => '+233 24 123 4567', 'items' => [$product->id => 1],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('orders', ['shop_id' => $shop->id, 'customer_phone' => '+233 24 123 4567', 'customer_email' => null, 'status' => 'paid']);
    $this->get(route('workspace.receipt', Order::firstOrFail()))->assertOk();
});

test('till and online checkout reject a missing or invalid phone', function (string $channel, ?string $phone) {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_key123']);
    $product = Product::factory()->for($shop)->approved()->create();
    $this->actingAs($shop->owner, 'backpack');
    $url = $channel === 'cash' ? route('workspace.sale', $shop) : route('checkout.store', $shop->slug);

    $this->post($url, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => $phone, 'items' => [$product->id => 1]])
        ->assertSessionHasErrors('customer_phone');
    $this->assertDatabaseCount('orders', 0);
    Http::assertNothingSent();
})->with(['cash', 'online'])->with([null, 'not a phone']);

test('online checkout retains Paystack email requirement', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_key123']);
    $product = Product::factory()->for($shop)->approved()->create();

    $this->post(route('checkout.store', $shop->slug), ['customer_name' => 'Buyer', 'customer_phone' => '+233241234567', 'items' => [$product->id => 1]])
        ->assertSessionHasErrors('customer_email');
    $this->assertDatabaseCount('orders', 0);
    Http::assertNothingSent();
});

test('storefront checkout creates manual cash and momo orders when paystack is not configured', function (string $paymentMethod) {
    $shop = Shop::factory()->approved()->create(['momo_number' => '+233241234567', 'momo_account_name' => 'Corner Shop Wallet']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 5, 'selling_price' => '10.00']);

    $this->post(route('checkout.store', $shop->slug), [
        'customer_name' => 'Manual Buyer',
        'customer_phone' => '+233241234567',
        'payment_method' => $paymentMethod,
        'items' => [$product->id => 2],
    ])->assertRedirect();

    $order = Order::firstOrFail();
    expect($order->channel)->toBe('manual');
    expect($order->payment_method)->toBe($paymentMethod);
    expect($order->status)->toBe('pending');
    expect($order->customer_email)->toBeNull();
    Http::assertNothingSent();

    $response = $this->get(route('checkout.callback', ['reference' => $order->reference]))->assertOk()->assertSee('Your order is reserved');
    if ($paymentMethod === 'momo') {
        $response->assertSee('Corner Shop Wallet')->assertSee('+233241234567');
        expect($response->getContent())->toMatch('/use reference [A-Z0-9]{7}\./')
            ->not->toContain('use reference '.$order->reference.'.');
    } else {
        $response->assertSee('Pay with cash');
    }
})->with(['cash', 'momo']);

test('shop staff can confirm pending manual payments and update stock', function (string $paymentMethod) {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true, 'momo_number' => '+233241234567', 'momo_account_name' => 'Corner Shop Wallet']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 5, 'selling_price' => '10.00']);

    $this->post(route('checkout.store', $shop->slug), [
        'customer_name' => 'Manual Buyer',
        'customer_phone' => '+233241234567',
        'payment_method' => $paymentMethod,
        'items' => [$product->id => 2],
    ])->assertRedirect();
    $order = Order::firstOrFail();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);

    $this->actingAs($shop->owner, 'backpack')->get(route('order.index'))
        ->assertOk()
        ->assertSee('Sales (1)')
        ->assertSee('Pending manual payments')
        ->assertSee('Manual payments awaiting confirmation')
        ->assertSee('1 pending')
        ->assertSee($order->reference)
        ->assertSee(route('workspace.sale.confirm-manual', [$shop, $order]), false);
    $this->post(route('workspace.sale.confirm-manual', [$shop, $order]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid', 'seller_id' => $shop->owner_id]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
    $this->assertDatabaseHas('inventory_movements', ['shop_id' => $shop->id, 'product_id' => $product->id, 'type' => 'sale', 'quantity' => -2, 'user_id' => $shop->owner_id]);

    $this->post(route('workspace.sale.confirm-manual', [$shop, $order]))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
})->with(['cash', 'momo']);

test('manual payment confirmation flags unavailable orders for review without overselling', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 2, 'selling_price' => '10.00']);

    $this->post(route('checkout.store', $shop->slug), [
        'customer_name' => 'Manual Buyer',
        'customer_phone' => '+233241234567',
        'payment_method' => 'cash',
        'items' => [$product->id => 2],
    ])->assertRedirect();
    $order = Order::firstOrFail();
    $product->forceFill(['quantity' => 1])->save();

    $this->actingAs($shop->owner, 'backpack')
        ->post(route('workspace.sale.confirm-manual', [$shop, $order]))
        ->assertRedirect()
        ->assertSessionHas('warning');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid_review', 'seller_id' => $shop->owner_id]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 1]);
    $this->assertDatabaseMissing('inventory_movements', ['shop_id' => $shop->id, 'product_id' => $product->id, 'type' => 'sale', 'quantity' => -2]);
});
