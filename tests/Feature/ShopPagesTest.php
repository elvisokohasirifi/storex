<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDiscount;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('shop overview separates forms and reports completed sales by currency', function () {
    $shop = Shop::factory()->create(['description' => "Our shop\nOur story"]);
    Product::factory()->for($shop)->approved()->create(['quantity' => 0]);
    Product::factory()->for($shop)->create(['quantity' => null]);
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 2500, 'currency' => 'GHS', 'paid_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 5000, 'currency' => 'NGN', 'paid_at' => now()]);
    Order::factory()->for($shop)->create(['status' => 'paid_review', 'total' => 99900]);
    Order::factory()->for($shop)->create(['status' => 'pending', 'total' => 99900]);
    Order::factory()->create(['status' => 'paid', 'total' => 900000]);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.show', $shop))->assertOk()
        ->assertViewHas('stats', ['sales' => 2, 'products' => 2, 'out_of_stock' => 1, 'users' => 1, 'payments_to_review' => 1])
        ->assertSee('GHS 25.00')->assertSee('NGN 50.00')
        ->assertSee('Edit shop')->assertSee(route('shop.edit', $shop))
        ->assertDontSee('Shop details')->assertDontSee('Our story')
        ->assertSee(route('shops.show', $shop->slug))->assertSee(route('workspace.payments', $shop))
        ->assertSee(route('workspace.users', $shop))->assertSee(route('workspace.till', $shop))
        ->assertSee(route('shop-discount.index', ['shop_id' => $shop->id]))
        ->assertDontSee('name="public_key"', false)->assertDontSee('name="csv"', false)
        ->assertDontSee('name="items[', false)->assertDontSee('name="password"', false);
});

test('payment and user forms are on their dedicated pages and never reveal saved keys', function () {
    $shop = Shop::factory()->create(['paystack_public_key' => 'pk_test_saved123', 'paystack_secret_key' => 'sk_test_saved123']);
    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.payments', $shop))->assertOk()
        ->assertSee('name="public_key"', false)->assertSee('name="secret_key"', false)
        ->assertDontSee('pk_test_saved123')->assertDontSee('sk_test_saved123');
    $this->get(route('workspace.users', $shop))->assertOk()->assertSee('Add or update shop user')->assertSee($shop->owner->name);
});

test('till shows shop products and records a sale on its own page', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create(['selling_price' => '7.50', 'quantity' => 3]);
    ShopDiscount::factory()->for($shop)->create(['name' => 'Till discount']);
    Product::factory()->for($shop)->draft()->create(['name' => 'Draft till item']);
    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.till', $shop))->assertOk()
        ->assertSee($product->name)->assertSee('GHS 6.75')->assertSee('Draft till item')->assertSee('Draft')->assertSee('Record cash sale');
    $this->post(route('workspace.sale', $shop), ['customer_name' => 'Walk in', 'customer_email' => 'walkin@example.com', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2]])->assertRedirect();
    $this->assertDatabaseHas('orders', ['shop_id' => $shop->id, 'status' => 'paid', 'total' => 1350]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 1]);
});

test('product forms and till expose device barcode scanner controls', function () {
    $shop = Shop::factory()->approved()->create();
    Product::factory()->for($shop)->approved()->create(['barcode' => '1234567890123']);

    $this->actingAs($shop->owner, 'backpack')->get(route('product.create', ['shop_id' => $shop->id]))->assertOk()
        ->assertSee('Visibility')
        ->assertSee('Sale Price')
        ->assertSee('Published')
        ->assertSee('Draft')
        ->assertSee('data-barcode-target="barcode"', false)
        ->assertSee('Scan barcode')
        ->assertSee('/barcode-scanner.js', false);

    $this->get(route('workspace.till', $shop))->assertOk()
        ->assertSee('data-barcode-pos', false)
        ->assertSee('data-submit-on-scan', false)
        ->assertSee('data-product-barcode="1234567890123"', false)
        ->assertSee('data-sale-quantity', false)
        ->assertSee('/barcode-scanner.js', false);
});

test('dedicated shop pages reject other tenants and platform admins', function (string $page) {
    $shop = Shop::factory()->create();
    $this->actingAs(User::factory()->create(), 'backpack')->get(route($page, $shop))->assertNotFound();
    $this->flushSession();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]), 'backpack')->get(route($page, $shop))->assertForbidden();
})->with(['workspace.payments', 'workspace.users', 'workspace.till']);

test('shop admins manage discount rules from their own page', function () {
    $shop = Shop::factory()->create();
    $category = $shop->categories()->create(['name' => 'Food']);

    $this->actingAs($shop->owner, 'backpack')->get(route('shop-discount.create', ['shop_id' => $shop->id]))->assertOk()
        ->assertSee('All products')->assertSee('A product category')->assertSee('A brand')->assertSee('Checkout total');

    $this->post(route('shop-discount.store'), [
        'shop_id' => $shop->id,
        'name' => 'Food sale',
        'scope' => 'category',
        'category_id' => $category->id,
        'type' => 'percentage',
        'value' => '15.00',
        'is_active' => 1,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('shop_discounts', ['shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Food sale']);
});

test('platform admins cannot access shop discounts', function () {
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]), 'backpack')
        ->get(route('shop-discount.index'))->assertForbidden();
});

test('store managers may use the till but cannot manage payment credentials or users', function () {
    $shop = Shop::factory()->approved()->create();
    $manager = User::factory()->create();
    $shop->members()->create(['user_id' => $manager->id, 'role' => 'manager']);
    $this->actingAs($manager, 'backpack')->get(route('workspace.till', $shop))->assertOk();
    $this->get(route('workspace.payments', $shop))->assertForbidden();
    $this->get(route('workspace.users', $shop))->assertForbidden();
});

test('owners preview their storefront regardless of approval without moderation or checkout controls', function (string $status) {
    $shop = Shop::factory()->create(['status' => $status]);
    Product::factory()->for($shop)->create(['name' => 'Owner pending item']);
    $this->actingAs($shop->owner, 'backpack')->get(route('shops.show', $shop->slug))->assertOk()
        ->assertSee('Owner preview')->assertSee('Owner pending item')->assertDontSee('Freeze shop')->assertDontSee('Reject shop')
        ->assertDontSee('name="customer_name"', false)->assertDontSee('name="items[', false)
        ->assertHeader('Cache-Control', 'no-store, private');
})->with(['pending', 'approved', 'rejected', 'frozen']);

test('bulk uploads are available from products and import into the selected shop', function () {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->get(route('product.index', ['shop_id' => $shop->id]))->assertOk()
        ->assertSee('Bulk product upload')->assertSee('name="csv_file"', false)->assertSee(route('products.bulk'));
    $file = UploadedFile::fake()->createWithContent('products.csv', "name,description,cost_price,selling_price,quantity,barcode,sku\nRice,Local rice,5,10,4,123,\n");
    $this->post(route('products.bulk'), ['shop_id' => $shop->id, 'csv_file' => $file])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['shop_id' => $shop->id, 'name' => 'Rice', 'status' => 'approved', 'visibility' => 'published']);
});

test('bulk upload cannot target another shop or a frozen shop', function () {
    $shop = Shop::factory()->create();
    $csv = "name,description,cost_price,selling_price,quantity,barcode,sku\nRice,,5,10,4,123,";
    $this->actingAs(User::factory()->create(), 'backpack')->post(route('products.bulk'), ['shop_id' => $shop->id, 'csv' => $csv])->assertNotFound();
    $this->flushSession();
    $shop->forceFill(['status' => 'frozen'])->save();
    $this->actingAs($shop->owner, 'backpack')->post(route('products.bulk'), ['shop_id' => $shop->id, 'csv' => $csv])->assertForbidden();
    $this->assertDatabaseCount('products', 0);
});

test('products table keeps the selected shop scope during ajax searches', function () {
    $owner = User::factory()->create();
    $shop = Shop::factory()->for($owner, 'owner')->create();
    $other = Shop::factory()->for($owner, 'owner')->create();
    Product::factory()->for($shop)->create(['name' => 'Selected shop product']);
    Product::factory()->for($other)->create(['name' => 'Different shop product']);

    $this->actingAs($owner, 'backpack')->post(route('product.search', ['shop_id' => $shop->id]), [
        'start' => 0, 'length' => 10, 'draw' => 1,
    ])->assertOk()->assertSee('Selected shop product')->assertDontSee('Different shop product');
});

test('sales today uses payment date boundaries and omits the recent sales table', function () {
    $this->travelTo(now()->setDate(2026, 9, 8)->setTime(12, 0));
    $shop = Shop::factory()->create();
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 1000, 'paid_at' => today(), 'created_at' => today()->subDay()]);
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 2000, 'paid_at' => today()->endOfDay()]);
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 8000, 'paid_at' => today()->subSecond()]);
    Order::factory()->for($shop)->create(['status' => 'paid', 'total' => 9000, 'paid_at' => today()->addDay()]);
    Order::factory()->for($shop)->create(['status' => 'paid_review', 'total' => 7000, 'paid_at' => now()]);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.show', $shop))->assertOk()
        ->assertSee('Sales today')->assertSee('GHS 30.00')->assertDontSee('Sales to date')
        ->assertDontSee('Recent sales &amp; payments', false)->assertViewMissing('orders');
});

test('sales today has an empty state when only historical sales exist', function () {
    $shop = Shop::factory()->create();
    Order::factory()->for($shop)->create(['status' => 'paid', 'paid_at' => today()->subDay()]);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.show', $shop))
        ->assertSee('No completed sales today.');
});
