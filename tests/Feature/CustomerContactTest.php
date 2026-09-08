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
