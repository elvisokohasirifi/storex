<?php

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDiscount;
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

test('storefront exposes products for approved shops and escapes descriptions', function () {
    $shop = Shop::factory()->approved()->create(['description' => "<script>bad()</script>\nNext line"]);
    Product::factory()->for($shop)->approved()->create(['name' => 'Visible product']);
    Product::factory()->for($shop)->create(['name' => 'Second visible product', 'status' => 'pending']);
    $this->get(route('shops.show', $shop->slug))->assertOk()->assertSee('Visible product')->assertSee('Second visible product')->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
    $shop->forceFill(['status' => 'frozen'])->save();
    $this->get(route('shops.show', $shop->slug))->assertNotFound();
});

test('draft products stay private on the storefront and cannot be bought online', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $published = Product::factory()->for($shop)->approved()->create(['name' => 'Published product']);
    $draft = Product::factory()->for($shop)->approved()->draft()->create(['name' => 'Draft product']);

    $this->get(route('shops.show', $shop->slug))->assertOk()
        ->assertSee('Published product')->assertDontSee('Draft product');

    $this->post(route('checkout.store', $shop->slug), [
        'customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567', 'items' => [$draft->id => 1],
    ])->assertSessionHasErrors('items');

    Http::assertNothingSent();
    expect($published->fresh()->visibility)->toBe('published');
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
    $this->actingAs($manager, 'backpack')->post(route('workspace.sale', $shop), ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2], 'total' => 1])->assertRedirect();
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

test('bulk import and duplication create products with safe stock defaults', function () {
    $shop = Shop::factory()->create();
    $csv = "name,description,cost_price,selling_price,quantity,barcode,sku\nBread,Fresh,,3,,123,";
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.bulk', $shop), ['csv' => $csv])->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    expect($product->quantity)->toBeNull();
    expect($product->visibility)->toBe('published');
    $this->post(route('workspace.duplicate', $product))->assertRedirect();
    expect(Product::count())->toBe(2);
    expect(Product::whereKeyNot($product->id)->first()->barcode)->toBeNull();
});

test('checkout reserves inventory and uses server prices', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 2]);
    $data = ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2]];
    $this->post(route('checkout.store', $shop->slug), $data + ['amount' => 1])->assertRedirect('https://checkout.paystack.com/test');
    expect(Order::first()->total)->toBe(2500);
    expect($product->fresh()->quantity)->toBe(2);
    $this->post(route('checkout.store', $shop->slug), $data)->assertSessionHasErrors('items');
    Http::assertSentCount(1);
});

test('sale prices and shop discounts reduce checkout totals server side', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/test']])]);
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 5, 'selling_price' => '12.50', 'sale_price' => '10.00']);
    ShopDiscount::factory()->for($shop)->create(['name' => 'Ten percent off', 'scope' => 'all_products', 'type' => 'percentage', 'value' => '10.00']);
    ShopDiscount::factory()->for($shop)->checkout()->fixed('2.00')->create(['name' => 'Checkout promo']);

    $this->post(route('checkout.store', $shop->slug), [
        'customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2],
    ])->assertRedirect('https://checkout.paystack.com/test');

    $order = Order::with('items')->firstOrFail();
    expect($order->subtotal)->toBe(2000);
    expect($order->discount_total)->toBe(400);
    expect($order->discount_name)->toBe('Product promotions + Checkout promo');
    expect($order->total)->toBe(1600);
    expect($order->items->first()->unit_price)->toBe(900);
    Http::assertSent(fn ($request) => $request['amount'] === 1600);
});

test('payment verification is idempotent and rejects mismatched amount', function () {
    $shop = Shop::factory()->approved()->create(['paystack_secret_key' => 'sk_test_secret']);
    $product = Product::factory()->for($shop)->approved()->create(['quantity' => 3]);
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567'], [$product->id => 2]);
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
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567'], [$product->id => 1]);
    $order->update(['expires_at' => now()->subMinute()]);
    app(SalesService::class)->create($shop, ['customer_name' => 'Cash', 'customer_email' => 'cash@example.com'], [$product->id => 1], $shop->owner);
    app(SalesService::class)->settle($order, ['status' => 'success', 'reference' => $order->reference, 'amount' => 1250, 'currency' => 'GHS', 'customer' => ['email' => 'buyer@example.com']]);
    expect($order->fresh()->status)->toBe('paid_review');
    expect($product->fresh()->quantity)->toBe(0);
});

test('Backpack screens render for an owner', function () {
    $shop = Shop::factory()->approved()->create();
    $this->actingAs($shop->owner, 'backpack');
    foreach (['workspace.index', 'shop.index', 'shop.create', 'product.index', 'product.create', 'shop-discount.index', 'order.index', 'finance.index', 'ledger-entry.create', 'account.security'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('workspace.show', $shop))->assertOk();
});

test('changed product details stay approved and submitted record IDs cannot redirect an update', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create(['name' => 'Original']);
    $other = Product::factory()->approved()->create(['name' => 'Other shop']);
    $this->actingAs($shop->owner, 'backpack')->put(route('product.update', $product), [
        'id' => $other->id, 'shop_id' => $shop->id, 'name' => 'Updated', 'selling_price' => '18.00', 'quantity' => 10, 'status' => 'approved', 'visibility' => 'draft',
    ])->assertSessionHasNoErrors();
    expect($product->fresh()->name)->toBe('Updated');
    expect($product->fresh()->status)->toBe('approved');
    expect($product->fresh()->visibility)->toBe('draft');
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
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567'], [$product->id => 2]);
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
    $order = app(SalesService::class)->create($shop, ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567'], [$product->id => 2]);
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
    $this->post(route('checkout.store', $shop->slug), ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '+233241234567', 'items' => [$product->id => 1]])->assertSessionHasErrors('payment');
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

test('platform overview counts records and lists shops needing approval', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $pending = Shop::factory()->for($owner, 'owner')->create(['name' => 'Awaiting shop approval']);
    $approved = Shop::factory()->for($owner, 'owner')->approved()->create(['name' => 'Approved shop']);
    $both = Shop::factory()->for($owner, 'owner')->create(['name' => 'Pending with products']);
    $clear = Shop::factory()->for($owner, 'owner')->approved()->create(['name' => 'Nothing pending']);
    $frozen = Shop::factory()->for($owner, 'owner')->create(['name' => 'Frozen shop', 'status' => 'frozen']);
    $rejected = Shop::factory()->for($owner, 'owner')->create(['name' => 'Rejected shop', 'status' => 'rejected']);
    $pending->members()->create(['user_id' => $staff->id, 'role' => 'manager']);
    $approved->members()->create(['user_id' => $staff->id, 'role' => 'manager']);
    Product::factory()->for($approved)->count(2)->create();
    Product::factory()->for($both)->create();
    Product::factory()->for($frozen)->create();
    Product::factory()->for($clear)->approved()->create();
    Product::factory()->for($rejected)->create(['status' => 'rejected']);

    $response = $this->actingAs($admin, 'backpack')->get(route('workspace.index'));
    $response->assertOk()->assertViewIs('admin.overview')
        ->assertViewHas('stats', ['shops' => 6, 'pending_shops' => 2, 'users' => 3, 'products' => 6])
        ->assertViewHas('shops', fn ($shops) => $shops->total() === 2 && $shops->getCollection()->modelKeys() === [$pending->id, $both->id])
        ->assertSee('Registered shops')->assertSee('Shops pending approval')->assertSee('Registered users')
        ->assertDontSee('Products pending approval')->assertDontSee('pending products')
        ->assertDontSee('Nothing pending')->assertDontSee('Rejected shop')->assertDontSee('Frozen shop')
        ->assertDontSee('href="'.route('product.index').'"', false)->assertDontSee(route('finance.index'), false);
});

test('platform overview keeps all attention shops reachable through pagination', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $owner = User::factory()->create();
    Shop::factory()->for($owner, 'owner')->count(21)->create();

    $this->actingAs($admin, 'backpack')->get(route('backpack.dashboard', ['page' => 2]))
        ->assertOk()->assertViewHas('shops', fn ($shops) => $shops->total() === 21 && $shops->count() === 1);
});

test('opening a shop from the admin list leads to its users and inventory', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $shop = Shop::factory()->create();
    Product::factory()->for($shop)->create(['name' => 'Shop-specific product']);

    $this->actingAs($admin, 'backpack')->get(route('shop.show', $shop))
        ->assertRedirect(route('workspace.show', $shop));
    $this->get(route('workspace.show', $shop))->assertOk()
        ->assertSee('Shop users')->assertSee($shop->owner->email)->assertSee('Shop-specific product')
        ->assertDontSee('href="'.route('product.index').'"', false);
});

test('empty platform overview shows zero totals and no attention needed', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin, 'backpack')->get(route('workspace.index'))->assertOk()
        ->assertViewHas('stats', ['shops' => 0, 'pending_shops' => 0, 'users' => 1, 'products' => 0])
        ->assertSee('All caught up');
});

test('shop approval also approves its products and returns to the details', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $shop = Shop::factory()->create();
    $pendingProduct = Product::factory()->for($shop)->create(['status' => 'pending']);
    $rejectedProduct = Product::factory()->for($shop)->create(['status' => 'rejected']);
    $otherProduct = Product::factory()->create(['status' => 'pending']);

    $this->actingAs($admin, 'backpack')->get(route('workspace.show', $shop))
        ->assertSee('Approve shop and products')->assertSee('Products')
        ->assertDontSee('Approve all products')->assertDontSee('Products moderation')
        ->assertDontSee('>All shops</a>', false)->assertDontSee('Inventory moderation');

    $this->from(route('workspace.show', $shop))->post(route('moderation.store'), [
        'type' => 'shops', 'ids' => [$shop->id], 'status' => 'approved',
    ])->assertRedirect(route('workspace.show', $shop))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'status' => 'approved']);
    $this->assertDatabaseHas('products', ['id' => $pendingProduct->id, 'status' => 'approved']);
    $this->assertDatabaseHas('products', ['id' => $rejectedProduct->id, 'status' => 'approved']);
    $this->assertDatabaseHas('products', ['id' => $otherProduct->id, 'status' => 'pending']);
    $this->assertDatabaseHas('moderation_logs', ['actor_id' => $admin->id, 'subject_id' => $shop->id, 'status' => 'approved']);
});

test('platform admins can preview shops and products without checkout', function (string $status) {
    $shop = Shop::factory()->create(['status' => $status, 'contacts' => '+233241234567', 'description' => "About our shop\nSecond line"]);
    Product::factory()->for($shop)->create(['name' => 'Pending preview item']);
    Product::factory()->for($shop)->create(['name' => 'Frozen preview item', 'status' => 'frozen']);
    Product::factory()->for($shop)->create(['name' => 'Rejected preview item', 'status' => 'rejected']);
    Product::factory()->for($shop)->approved()->create(['name' => 'Approved preview item']);
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin, 'backpack')->get(route('shops.show', $shop->slug))
        ->assertOk()->assertSee('Admin preview')->assertSee('Pending preview item')->assertSee('Frozen preview item')
        ->assertSee('Rejected preview item')->assertSee('Approved preview item')->assertSee('About our shop')
        ->assertSee('href="#products"', false)->assertSee('href="#contact"', false)
        ->assertSee('href="tel:+233241234567"', false)->assertSee('mailto:'.$shop->email)
        ->assertDontSee('name="customer_name"', false)->assertDontSee('name="items[', false)
        ->assertDontSee('Online payments are not enabled')->assertHeader('Cache-Control', 'no-store, private');
})->with(['pending', 'approved', 'rejected', 'frozen']);

test('guests and unrelated users cannot use the storefront to preview unapproved shops', function (string $status) {
    $shop = Shop::factory()->create(['status' => $status]);
    $this->get(route('shops.show', $shop->slug, ['preview' => 1]))->assertNotFound();
    $this->actingAs(User::factory()->create(), 'backpack')->get(route('shops.show', $shop->slug, ['preview' => 1]))->assertNotFound();
})->with(['pending', 'rejected', 'frozen']);

test('admins can freeze or reject a shop from the storefront with an audited reason', function (string $status) {
    $shop = Shop::factory()->approved()->create();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin, 'backpack')->get(route('shops.show', $shop->slug))
        ->assertSee('Freeze shop')->assertSee('Reject shop');

    $this->from(route('shops.show', $shop->slug))->post(route('moderation.store'), [
        'type' => 'shops', 'ids' => [$shop->id], 'status' => $status, 'reason' => 'Shop details need review.',
    ])->assertRedirect(route('shops.show', $shop->slug))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'status' => $status]);
    $this->assertDatabaseHas('moderation_logs', ['actor_id' => $admin->id, 'subject_id' => $shop->id, 'status' => $status, 'reason' => 'Shop details need review.']);
    $this->get(route('shops.show', $shop->slug))->assertOk()->assertSee('Admin preview');
})->with(['frozen', 'rejected']);

test('storefront moderation requires a reason and stays hidden from public visitors', function () {
    $shop = Shop::factory()->approved()->create();
    $this->get(route('shops.show', $shop->slug))->assertDontSee('Freeze shop')->assertDontSee('Reject shop');
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin, 'backpack')->post(route('moderation.store'), [
        'type' => 'shops', 'ids' => [$shop->id], 'status' => 'frozen',
    ])->assertSessionHasErrors('reason');
    $this->assertDatabaseHas('shops', ['id' => $shop->id, 'status' => 'approved']);
    $this->assertDatabaseCount('moderation_logs', 0);
});

test('shop owners reach the team form through the shops preview', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->get(route('shop.show', $shop))
        ->assertRedirect(route('workspace.show', $shop));
    $this->get(route('workspace.show', $shop))->assertOk()->assertSee(route('workspace.users', $shop));
    $this->get(route('workspace.users', $shop))->assertOk()->assertSee('Add or update shop user')
        ->assertSee(route('workspace.member', $shop));
});

test('owners can add a new shop user and change an existing users shop role without resetting their password', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.member', $shop), [
        'name' => 'Shop assistant', 'email' => 'assistant@example.com', 'password' => 'initial-password-123', 'role' => 'manager',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $staff = User::where('email', 'assistant@example.com')->firstOrFail();
    $originalHash = $staff->password;
    $this->assertDatabaseHas('shop_members', ['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'manager']);

    $this->post(route('workspace.member', $shop), [
        'name' => 'Shop assistant', 'email' => $staff->email, 'password' => 'different-password-123', 'role' => 'admin',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('shop_members', ['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'admin']);
    expect($staff->fresh()->password)->toBe($originalHash);
});
