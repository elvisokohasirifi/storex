<?php

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    Http::preventStrayRequests();
});

test('owners create UUID shops without being able to approve themselves', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner, 'backpack')->post(route('shop.store'), [
        'name' => 'Corner Store', 'location' => 'Accra', 'contacts' => '+233241234567',
        'email' => 'shop@example.com', 'description' => "First line\nSecond line",
        'status' => 'approved', 'owner_id' => 'spoofed',
    ])->assertSessionHasNoErrors();
    $shop = Shop::firstOrFail();
    expect(Str::isUuid($shop->id))->toBeTrue();
    expect($shop->owner_id)->toBe($owner->id);
    expect($shop->status)->toBe('pending');
    expect($shop->description)->toBe("First line\nSecond line");
});

test('storefront only exposes approved shops and products and escapes descriptions', function () {
    $shop = Shop::factory()->approved()->create(['description' => "<script>bad()</script>\nNext line"]);
    Product::factory()->for($shop)->approved()->create(['name' => 'Visible product']);
    Product::factory()->for($shop)->create(['name' => 'Hidden product']);
    $this->get(route('shops.show', $shop->slug))->assertOk()->assertSee('Visible product')->assertDontSee('Hidden product')->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
    $shop->forceFill(['status' => 'frozen'])->save();
    $this->get(route('shops.show', $shop->slug))->assertNotFound();
});

test('cross shop reads updates and duplicates are rejected', function () {
    $owner = User::factory()->create();
    $other = Shop::factory()->approved()->create();
    $product = Product::factory()->for($other)->approved()->create();
    $this->actingAs($owner, 'backpack')->get(route('workspace.show', $other))->assertNotFound();
    $this->get(route('product.edit', $product))->assertNotFound();
    $this->post(route('workspace.duplicate', $product))->assertNotFound();
    $this->put(route('product.update', $product), ['shop_id' => $other->id, 'name' => 'Tampered'])->assertNotFound();
    expect($product->fresh()->name)->not->toBe('Tampered');
});

test('platform admins moderate in bulk but cannot create or edit business records', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $shops = Shop::factory()->count(2)->create();
    $this->actingAs($admin, 'backpack')->post(route('moderation.store'), ['type' => 'shops', 'ids' => $shops->modelKeys(), 'status' => 'approved'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('moderation_logs', 2);
    expect(Shop::where('status', 'approved')->count())->toBe(2);
    $this->post(route('shop.store'), ['name' => 'Forbidden'])->assertForbidden();
    $this->put(route('shop.update', $shops->first()), ['name' => 'Forbidden'])->assertForbidden();
    $this->get(route('workspace.show', $shops->first()))->assertOk()->assertDontSee('name="secret_key"', false);
    $this->post(route('workspace.credentials', $shops->first()), ['currency' => 'GHS'])->assertForbidden();
});

test('manager can sell but cannot change team credentials or moderation', function () {
    $shop = Shop::factory()->approved()->create();
    $manager = User::factory()->create();
    $shop->members()->create(['user_id' => $manager->id, 'role' => 'manager']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 3, 'selling_price' => '12.50']);
    $this->actingAs($manager, 'backpack')->post(route('workspace.sale', $shop), ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'items' => [$product->id => 2], 'total' => 1])->assertRedirect();
    expect(Order::first()->total)->toBe(2500);
    expect($product->fresh()->quantity)->toBe(1);
    $this->post(route('workspace.credentials', $shop), ['currency' => 'GHS'])->assertForbidden();
    $this->post(route('workspace.member', $shop), [])->assertForbidden();
    $this->post(route('moderation.store'), [])->assertForbidden();
});

test('bulk import rolls back all rows on duplicate barcodes', function () {
    $shop = Shop::factory()->create();
    $csv = "name,description,cost_price,selling_price,quantity,barcode,sku\nBread,,2,3,4,123,\nMilk,,2,3,4,123,";
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.bulk', $shop), ['csv' => $csv])->assertSessionHasErrors('csv');
    $this->assertDatabaseCount('products', 0);
});

test('bulk import and duplication submit products with safe stock and approval defaults', function () {
    $shop = Shop::factory()->create();
    $csv = "name,description,cost_price,selling_price,quantity,barcode,sku\nBread,Fresh,,3,,123,";
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.bulk', $shop), ['csv' => $csv])->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    expect($product->quantity)->toBeNull();
    $this->post(route('workspace.duplicate', $product))->assertRedirect();
    expect(Product::count())->toBe(2);
    expect(Product::whereKeyNot($product->id)->first()->barcode)->toBeNull();
});

test('checkout reserves inventory and uses server prices', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 2]);
    $data = ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'items' => [$product->id => 2]];
    $this->post(route('checkout.store', $shop->slug), $data + ['amount' => 1])->assertRedirect('https://checkout.paystack.com/test');
    expect(Order::first()->total)->toBe(2500);
    expect($product->fresh()->quantity)->toBe(2);
    $this->post(route('checkout.store', $shop->slug), $data)->assertSessionHasErrors('items');
    Http::assertSentCount(1);
});

test('payment verification is idempotent and rejects mismatched amount', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 3]);
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com'], [$product->id => 2]);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 2500, 'currency' => 'GHS', 'customer' => ['email' => 'buyer@example.com']]])]);
    $this->get(route('checkout.callback', ['reference' => $order->reference]))->assertOk()->assertSee('Paid');
    $this->get(route('checkout.callback', ['reference' => $order->reference]))->assertOk();
    expect($product->fresh()->quantity)->toBe(1);
    expect($order->fresh()->status)->toBe('paid');
    Http::assertSentCount(2);
});

test('invalid webhook signatures cannot mark orders paid', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $this->postJson(route('checkout.webhook', $shop), ['event' => 'charge.success', 'data' => ['reference' => 'fake']])->assertUnauthorized();
    Http::assertNothingSent();
});

test('late paid orders with unavailable stock are flagged for review without overselling', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 1]);
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com'], [$product->id => 1]);
    $order->update(['expires_at' => now()->subMinute()]);
    app(SalesService::class)->create($shop, ['customer_name' => 'Cash', 'customer_email' => 'cash@example.com'], [$product->id => 1], $shop->owner);
    app(SalesService::class)->settle($order, ['status' => 'success', 'reference' => $order->reference, 'amount' => 1250, 'currency' => 'GHS', 'customer' => ['email' => 'buyer@example.com']]);
    expect($order->fresh()->status)->toBe('paid_review');
    expect($product->fresh()->quantity)->toBe(0);
});

test('Backpack screens render for an owner', function () {
    $shop = Shop::factory()->approved()->create();
    $this->actingAs($shop->owner, 'backpack');
    foreach (['workspace.index', 'shop.index', 'shop.create', 'product.index', 'product.create', 'order.index', 'finance.index', 'ledger-entry.create', 'account.security'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('workspace.show', $shop))->assertOk();
});

test('changed product details return to pending and submitted record IDs cannot redirect an update', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create(['name' => 'Original']);
    $other = Product::factory()->approved()->create(['name' => 'Other shop']);
    $this->actingAs($shop->owner, 'backpack')->put(route('product.update', $product), [
        'id' => $other->id, 'shop_id' => $shop->id, 'name' => 'Updated', 'selling_price' => '18.00', 'quantity' => 10, 'status' => 'approved',
    ])->assertSessionHasNoErrors();
    expect($product->fresh()->name)->toBe('Updated');
    expect($product->fresh()->status)->toBe('pending');
    expect($other->fresh()->name)->toBe('Other shop');
});

test('Paystack credentials are encrypted and do not appear in rendered forms', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.credentials', $shop), ['secret_key' => 'sk_test_private123', 'public_key' => 'pk_test_public123', 'currency' => 'GHS'])->assertSessionHasNoErrors();
    expect($shop->fresh()->paystack_secret_key)->toBe('sk_test_private123');
    expect(DB::table('shops')->where('id', $shop->id)->value('paystack_secret_key'))->not->toContain('sk_test_private123');
    $this->get(route('workspace.show', $shop))->assertOk()->assertDontSee('sk_test_private123')->assertDontSee('pk_test_public123');
});

test('shop admins can add staff and removing membership revokes access', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.member', $shop), ['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'safe-initial-password', 'role' => 'manager'])->assertSessionHasNoErrors();
    $staff = User::where('email', 'staff@example.com')->firstOrFail();
    $member = $shop->members()->firstOrFail();
    $this->flushSession();
    $this->actingAs($staff, 'backpack')->get(route('workspace.show', $shop))->assertOk();
    $this->flushSession();
    $this->actingAs($shop->owner, 'backpack')->delete(route('workspace.member.remove', [$shop, $member]))->assertRedirect();
    $this->flushSession();
    $this->actingAs($staff, 'backpack')->get(route('workspace.show', $shop))->assertNotFound();
});

test('payment mismatch leaves the order pending and inventory unchanged', function (string $field, mixed $value) {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 3]);
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com'], [$product->id => 2]);
    $payment = ['status' => 'success', 'reference' => $order->reference, 'amount' => 2500, 'currency' => 'GHS', 'customer' => ['email' => 'buyer@example.com']];
    data_set($payment, $field, $value);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => $payment])]);
    $this->get(route('checkout.callback', ['reference' => $order->reference]))->assertOk()->assertSee('not confirmed yet');
    expect($order->fresh()->status)->toBe('pending');
    expect($product->fresh()->quantity)->toBe(3);
    Http::assertSentCount(1);
})->with(['amount' => ['amount', 1], 'currency' => ['currency', 'NGN'], 'reference' => ['reference', 'wrong'], 'customer' => ['customer.email', 'wrong@example.com'], 'status' => ['status', 'failed']]);

test('valid signed Paystack webhook settles a payment once', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 3]);
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com'], [$product->id => 2]);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 2500, 'currency' => 'GHS', 'customer' => ['email' => 'buyer@example.com']]])]);
    $payload = ['event' => 'charge.success', 'data' => ['reference' => $order->reference]];
    $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_secret');
    $this->postJson(route('checkout.webhook', $shop), $payload, ['x-paystack-signature' => $signature])->assertOk();
    $this->postJson(route('checkout.webhook', $shop), $payload, ['x-paystack-signature' => $signature])->assertOk();
    expect($order->fresh()->status)->toBe('paid');
    expect($product->fresh()->quantity)->toBe(1);
    Http::assertSentCount(2);
});

test('failed payment initialization releases its stock reservation', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 1]);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => false], 500)]);
    $this->post(route('checkout.store', $shop->slug), ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'items' => [$product->id => 1]])->assertSessionHasErrors('payment');
    expect(Order::first()->status)->toBe('cancelled');
    expect(app(SalesService::class)->available($product))->toBe(1);
    Http::assertSentCount(1);
});

test('platform admins cannot access financial pages searches exports or receipts even for owned shops', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $shop = Shop::factory()->for($admin, 'owner')->approved()->create();
    $order = Order::factory()->for($shop)->create();
    $entry = LedgerEntry::factory()->for($shop)->create();

    $this->actingAs($admin, 'backpack');
    foreach ([
        route('order.index'), route('order.show', $order),
        route('order.showDetailsRow', $order), route('workspace.receipt', $order),
        route('ledger-entry.index'), route('ledger-entry.show', $entry),
        route('ledger-entry.showDetailsRow', $entry), route('ledger-entry.create'),
        route('ledger-entry.edit', $entry), route('finance.index'),
        route('finance.index', ['shop_id' => $shop->id, 'export' => 1]),
        route('finance.receipt', $entry),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->postJson(route('order.search'), [])->assertForbidden();
    $this->postJson(route('ledger-entry.search'), [])->assertForbidden();
    $this->post(route('ledger-entry.store'), [])->assertForbidden();
    $this->put(route('ledger-entry.update', $entry), [])->assertForbidden();
});

test('platform admin workspace shows shop users and inventory without financial information or navigation', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $shop = Shop::factory()->approved()->create();
    $staff = User::factory()->create(['name' => 'Moderation-visible manager']);
    $shop->members()->create(['user_id' => $staff->id, 'role' => 'manager']);
    Product::factory()->for($shop)->create(['name' => 'Inventory under review']);
    Order::factory()->for($shop)->create(['customer_name' => 'Private sales customer']);

    $this->actingAs($admin, 'backpack')->get(route('workspace.show', $shop))
        ->assertOk()->assertViewIs('admin.shop-moderation')
        ->assertViewMissing('orders')->assertViewMissing('salesTotal')
        ->assertSee($shop->owner->email)->assertSee($staff->email)->assertSee('Inventory under review')
        ->assertDontSee('Private sales customer')->assertDontSee('COMPLETED SALES')
        ->assertDontSee('ONLINE PAYMENTS')->assertDontSee('Paystack settings')
        ->assertDontSee(route('order.index'), false)->assertDontSee(route('ledger-entry.index'), false)
        ->assertDontSee(route('finance.index'), false);
    $this->get(route('shop.index'))->assertOk();
    $this->get(route('product.index'))->assertOk();
    $this->get(route('moderation.index'))->assertOk();
});
