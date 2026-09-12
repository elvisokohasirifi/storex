<?php

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDiscount;
use App\Models\Supplier;
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
        ->assertSee($product->name)->assertSee('GHS 6.75')->assertSee('Draft till item')->assertSee('Draft')
        ->assertSee('<table', false)->assertSee('<th>Product</th>', false)->assertSee('<th>Cart</th>', false)
        ->assertSee('Add to cart')->assertSee('Cart')->assertSee('data-cart-total', false)
        ->assertSee('class="fs-5 fw-semibold lh-sm"', false)->assertSee('data-cart-decrement', false)->assertSee('data-cart-increment', false)
        ->assertSee('Record cash sale');
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
        ->assertSee('data-cart-add', false)
        ->assertSee('/barcode-scanner.js', false);
});

test('public storefront uses cart buttons while admin preview stays moderation only', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create(['name' => 'Cartable bread', 'quantity' => 5, 'selling_price' => '8.00']);

    $this->get(route('shops.show', $shop->slug))->assertOk()
        ->assertSee('Add to cart')
        ->assertSee(route('cart.show', $shop->slug))
        ->assertSee('data-cart-form', false)
        ->assertSee('data-cart-count', false)
        ->assertSee('data-cart-popover', false)
        ->assertSee('/js/storefront-cart.js', false)
        ->assertSee('Your cart is waiting')
        ->assertDontSee('name="customer_name"', false)
        ->assertDontSee('action="'.route('checkout.store', $shop->slug).'"', false);

    $cartResponse = $this->postJson(route('cart.add', $shop->slug), ['product_id' => $product->id, 'quantity' => 2])->assertOk()
        ->assertJsonPath('message', 'Cartable bread added to cart.')
        ->assertJsonPath('cart_count', 2)
        ->assertJsonPath('cart_total', 'GHS 16.00');
    expect($cartResponse->json('cart_popover'))->toContain('Cartable bread × 2');
    $this->get(route('cart.show', $shop->slug))->assertOk()
        ->assertSee('Your cart')
        ->assertSee('Cartable bread')
        ->assertSee('name="items['.$product->id.']"', false)
        ->assertSee('GHS 16.00')
        ->assertSee(route('checkout.show', $shop->slug));

    $this->put(route('cart.update', $shop->slug), ['items' => [$product->id => 3]])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('checkout.show', $shop->slug))->assertOk()
        ->assertSee('Checkout')
        ->assertSee('Cartable bread × 3')
        ->assertSee('Pay with cash')
        ->assertSee('Email address <span>(optional)</span>', false)
        ->assertSee('GHS 24.00')
        ->assertSeeInOrder(['Order summary', 'Place order']);

    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->flushSession();
    $this->actingAs($admin, 'backpack')->get(route('shops.show', $shop->slug))->assertOk()
        ->assertSee('Admin preview')
        ->assertSee('Freeze shop')
        ->assertDontSee('Add to cart')
        ->assertDontSee(route('cart.show', $shop->slug));
});

test('dedicated shop pages reject other tenants and platform admins', function (string $page) {
    $shop = Shop::factory()->create();
    $this->actingAs(User::factory()->create(), 'backpack')->get(route($page, $shop))->assertNotFound();
    $this->flushSession();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]), 'backpack')->get(route($page, $shop))->assertForbidden();
})->with(['workspace.payments', 'workspace.users', 'workspace.till', 'workspace.inventory']);

test('inventory management has a dedicated owner dashboard page', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    Product::factory()->for($shop)->create(['name' => 'Fresh Bread', 'quantity' => 6, 'reorder_level' => 10, 'cost_price' => '5.00', 'selling_price' => '10.00']);
    Product::factory()->for($shop)->create(['name' => 'Milk 1L', 'quantity' => 20, 'reorder_level' => 10, 'cost_price' => '3.00', 'selling_price' => '6.00']);

    $this->actingAs($shop->owner, 'backpack')->get(route('inventory-management.index'))->assertOk()
        ->assertSee('Inventory management')
        ->assertSee('Shop inventory workspaces')
        ->assertSee('Inventory-enabled shops')
        ->assertSee('Low-stock products')
        ->assertSee($shop->name)
        ->assertSee('Open inventory')
        ->assertSee(route('workspace.inventory', $shop))
        ->assertSee('GHS 90.00')
        ->assertSee('GHS 180.00');
});

test('platform admins cannot access the dedicated inventory management page', function () {
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]), 'backpack')
        ->get(route('inventory-management.index'))->assertForbidden();
});

test('inventory management page shows valuation low stock and records stock changes', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $bread = Product::factory()->for($shop)->create(['name' => 'Fresh Bread', 'sku' => 'BREAD-01', 'barcode' => '5449000000996', 'quantity' => 6, 'reorder_level' => 10, 'cost_price' => '5.00', 'selling_price' => '10.00']);
    Product::factory()->for($shop)->create(['name' => 'Cooking Oil 1L', 'quantity' => 12, 'reorder_level' => 10, 'cost_price' => '2.00', 'selling_price' => '4.00']);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.inventory', $shop))->assertOk()
        ->assertSee('Receive Stock')
        ->assertSee('Stock Adjustment')
        ->assertSee('Returns')
        ->assertSee('Fresh Bread')
        ->assertSee('6 remaining')
        ->assertSee('Suggested quantity: 24')
        ->assertSee('GHS 54.00')
        ->assertSee('GHS 108.00');

    $this->post(route('workspace.inventory.receive', $shop), ['product_id' => $bread->id, 'quantity' => 4, 'unit_cost' => '6.00', 'reason' => 'Supplier delivery'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $bread->id, 'quantity' => 10, 'cost_price' => '6']);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $bread->id, 'type' => 'purchase', 'quantity' => 4, 'reason' => 'Supplier delivery']);

    $this->post(route('workspace.inventory.adjust', $shop), ['product_id' => $bread->id, 'actual_quantity' => 7, 'reason' => 'damage', 'notes' => '3 loaves damaged during delivery'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $bread->id, 'quantity' => 7]);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $bread->id, 'type' => 'damage', 'quantity' => -3, 'reason' => 'Damage: 3 loaves damaged during delivery']);

    $this->post(route('workspace.inventory.return', $shop), ['product_id' => $bread->id, 'quantity' => 2, 'reason' => 'Customer return'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $bread->id, 'quantity' => 9]);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $bread->id, 'type' => 'return', 'quantity' => 2, 'reason' => 'Customer return']);
});

test('stock details page shows current stock and movement history', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $product = Product::factory()->for($shop)->create(['name' => 'Coca-Cola 500ml', 'sku' => 'COKE-500', 'barcode' => '5449000000996', 'quantity' => 48, 'reorder_level' => 10, 'cost_price' => '5.00']);
    InventoryMovement::factory()->for($shop)->for($product)->create(['type' => 'purchase', 'quantity' => 100, 'reason' => 'Stock received']);
    InventoryMovement::factory()->for($shop)->for($product)->create(['type' => 'sale', 'quantity' => -2, 'reason' => 'Sale #1023']);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.stock', [$shop, $product]))->assertOk()
        ->assertSee('Coca-Cola 500ml')
        ->assertSee('SKU: COKE-500')
        ->assertSee('Barcode: 5449000000996')
        ->assertSee('Current Stock')
        ->assertSee('48')
        ->assertSee('Reorder Level')
        ->assertSee('Average Cost')
        ->assertSee('GHS 5.00')
        ->assertSee('Stock Value')
        ->assertSee('GHS 240.00')
        ->assertSee('+100')
        ->assertSee('Stock received')
        ->assertSee('-2')
        ->assertSee('Sale #1023');
});

test('inventory managed shops require cost and quantity and log opening stock and sales', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);

    $this->actingAs($shop->owner, 'backpack')->post(route('products.bulk'), [
        'shop_id' => $shop->id,
        'csv' => "name,description,cost_price,selling_price,quantity,barcode,sku\nBread,,5,10,,123,BREAD-01",
    ])->assertSessionHasErrors('csv');

    $this->post(route('products.bulk'), [
        'shop_id' => $shop->id,
        'csv' => "name,description,cost_price,selling_price,quantity,barcode,sku\nBread,,5,10,4,123,BREAD-01",
    ])->assertRedirect()->assertSessionHasNoErrors();

    $product = Product::where('shop_id', $shop->id)->where('name', 'Bread')->firstOrFail();
    expect($product->reorder_level)->toBe(10);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'type' => 'opening_stock', 'quantity' => 4]);

    $this->post(route('workspace.sale', $shop), ['customer_name' => 'Walk in', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2]])->assertRedirect();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 2]);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => -2]);
});

test('shop operations page manages suppliers purchase orders shifts variants transfers and exports', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $targetShop = Shop::factory()->for($shop->owner, 'owner')->approved()->create(['enable_inventory_management' => true]);
    $product = Product::factory()->for($shop)->create(['name' => 'Fresh Bread', 'sku' => 'BREAD-01', 'quantity' => 6, 'reorder_level' => 10, 'cost_price' => '5.00', 'selling_price' => '10.00']);

    $this->actingAs($shop->owner, 'backpack')->get(route('workspace.operations', $shop))->assertOk()
        ->assertSee('Shop operations')
        ->assertSee('Till shift')
        ->assertSee('Supplier')
        ->assertSee('Create purchase order')
        ->assertSee('Product variant')
        ->assertSee('Transfer stock')
        ->assertSee('Bulk price update')
        ->assertDontSee('Manual payments awaiting confirmation')
        ->assertSee(route('workspace.exports.products', $shop));

    $this->post(route('workspace.supplier', $shop), ['name' => 'Daily Bakery', 'phone' => '+233241234567'])->assertRedirect()->assertSessionHasNoErrors();
    $supplier = Supplier::where('shop_id', $shop->id)->where('name', 'Daily Bakery')->firstOrFail();

    $this->post(route('workspace.purchase-order', $shop), ['supplier_id' => $supplier->id, 'product_id' => $product->id, 'quantity' => 5, 'unit_cost' => '4.00', 'notes' => 'Weekly order'])->assertRedirect()->assertSessionHasNoErrors();
    $purchaseOrder = $shop->purchaseOrders()->firstOrFail();
    $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'quantity' => 5]);

    $this->post(route('workspace.purchase-order.receive', [$shop, $purchaseOrder]))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 11]);
    $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'received']);
    $this->assertDatabaseHas('stock_batches', ['product_id' => $product->id, 'supplier_id' => $supplier->id, 'quantity_received' => 5]);

    $this->post(route('workspace.purchase-order.receive', [$shop, $purchaseOrder]))->assertUnprocessable();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 11]);

    $this->post(route('workspace.variant', $shop), ['product_id' => $product->id, 'name' => 'Family pack', 'selling_price' => '18.00', 'quantity' => 2, 'reorder_level' => 4])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'name' => 'Family pack']);

    $this->post(route('workspace.transfer', $shop), ['target_shop_id' => $targetShop->id, 'product_id' => $product->id, 'quantity' => 3, 'reason' => 'Branch restock'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 8]);
    $this->assertDatabaseHas('inventory_movements', ['shop_id' => $shop->id, 'product_id' => $product->id, 'type' => 'transfer_out', 'quantity' => -3]);
    $this->assertDatabaseHas('inventory_movements', ['shop_id' => $targetShop->id, 'type' => 'transfer_in', 'quantity' => 3]);

    $this->post(route('workspace.bulk-prices', $shop), ['csv' => "sku,barcode,selling_price,sale_price\nBREAD-01,,11.00,9.00"])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'selling_price' => '11', 'sale_price' => '9']);

    $this->get(route('workspace.exports.products', $shop))->assertOk()->assertHeader('content-disposition');
});

test('till shifts customer records and payment methods are tracked for sales', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $product = Product::factory()->for($shop)->create(['quantity' => 5, 'cost_price' => '5.00', 'selling_price' => '10.00']);

    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.shift.open', $shop), ['opening_cash' => '20.00'])->assertRedirect()->assertSessionHasNoErrors();
    $shift = $shop->tillShifts()->firstOrFail();

    $this->post(route('workspace.sale', $shop), ['customer_name' => 'Ama Customer', 'customer_phone' => '+233241234567', 'payment_method' => 'momo', 'items' => [$product->id => 2]])->assertRedirect();
    $order = Order::where('shop_id', $shop->id)->firstOrFail();
    expect($order->receipt_number)->not->toBeNull();
    $this->assertDatabaseHas('customers', ['shop_id' => $shop->id, 'phone' => '+233241234567', 'name' => 'Ama Customer']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_method' => 'momo', 'till_shift_id' => $shift->id]);

    $this->post(route('workspace.shift.close', [$shop, $shift]), ['actual_cash' => '20.00', 'notes' => 'Momo sale settled separately'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('till_shifts', ['id' => $shift->id, 'status' => 'closed', 'actual_cash' => 2000]);
});

test('refunds and cancellations reverse tracked stock once', function () {
    $shop = Shop::factory()->approved()->create(['enable_inventory_management' => true]);
    $product = Product::factory()->for($shop)->create(['quantity' => 5, 'cost_price' => '5.00', 'selling_price' => '10.00']);

    $this->actingAs($shop->owner, 'backpack')->post(route('workspace.sale', $shop), ['customer_name' => 'Walk in', 'customer_phone' => '+233241234567', 'items' => [$product->id => 2]])->assertRedirect();
    $order = Order::where('shop_id', $shop->id)->firstOrFail();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);

    $this->post(route('workspace.sale.refund', [$shop, $order]), ['reason' => 'Customer returned items'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded', 'status_reason' => 'Customer returned items']);
    $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'type' => 'return', 'quantity' => 2, 'reason' => 'Refunded reversal: Customer returned items']);

    $this->post(route('workspace.sale.cancel', [$shop, $order]), ['reason' => 'Duplicate'])->assertSessionHasErrors('order');
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);
});

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

test('storefront header shows the logo without repeating the shop name beside it', function () {
    $shop = Shop::factory()->approved()->create(['name' => 'Wide Logo Shop', 'logo' => 'wide-logo.png']);

    $this->get(route('shops.show', $shop->slug))->assertOk()
        ->assertSee('alt="Wide Logo Shop logo"', false)
        ->assertDontSee('<span>Wide Logo Shop</span>', false);
});

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
