<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;

test('shop owners can create and update categories and brands with UUIDs', function (string $model, string $route) {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route($route.'.store'), [
        'shop_id' => $shop->id, 'name' => 'Pantry', 'description' => "Line one\nLine two",
    ])->assertSessionHasNoErrors();
    $entry = $model::firstOrFail();
    expect(Str::isUuid($entry->id))->toBeTrue();
    expect($entry->shop_id)->toBe($shop->id);
    expect($entry->description)->toBe("Line one\nLine two");

    $this->put(route($route.'.update', $entry), ['shop_id' => $shop->id, 'name' => 'Groceries'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas($entry->getTable(), ['id' => $entry->id, 'name' => 'Groceries']);
})->with([[ProductCategory::class, 'product-category'], [Brand::class, 'brand']]);

test('classification management is tenant scoped and platform admins are read only', function (string $model, string $route) {
    $shop = Shop::factory()->create();
    $entry = $model::factory()->for($shop)->create();
    $outsider = User::factory()->create();
    $this->actingAs($outsider, 'backpack')->get(route($route.'.show', $entry))->assertNotFound();
    $this->post(route($route.'.store'), ['shop_id' => $shop->id, 'name' => 'Tampered'])->assertForbidden();
    $this->put(route($route.'.update', $entry), ['shop_id' => $shop->id, 'name' => 'Tampered'])->assertNotFound();
    $this->flushSession();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $this->actingAs($admin, 'backpack')->get(route($route.'.show', $entry))->assertOk();
    $this->post(route($route.'.store'), ['shop_id' => $shop->id, 'name' => 'Tampered'])->assertForbidden();
    $this->put(route($route.'.update', $entry), ['shop_id' => $shop->id, 'name' => 'Tampered'])->assertForbidden();
    $this->assertDatabaseHas($entry->getTable(), ['id' => $entry->id, 'name' => $entry->name]);
})->with([[ProductCategory::class, 'product-category'], [Brand::class, 'brand']]);

test('products accept same shop classifications and reject classifications from other shops', function () {
    $shop = Shop::factory()->create();
    $category = ProductCategory::factory()->for($shop)->create();
    $brand = Brand::factory()->for($shop)->create();
    $payload = ['shop_id' => $shop->id, 'name' => 'Rice', 'selling_price' => '15.00', 'category_id' => $category->id, 'brand_id' => $brand->id];
    $this->actingAs($shop->owner, 'backpack')->post(route('product.store'), $payload)->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    expect($product->category->is($category))->toBeTrue();
    expect($product->brand->is($brand))->toBeTrue();
    expect($category->products->contains($product))->toBeTrue();
    expect($shop->brands->contains($brand))->toBeTrue();

    $this->post(route('product.store'), array_replace($payload, [
        'category_id' => ProductCategory::factory()->create()->id,
        'brand_id' => Brand::factory()->create()->id,
    ]))->assertSessionHasErrors(['category_id', 'brand_id']);
    $this->assertDatabaseCount('products', 1);
});

test('classification changes keep approved products approved', function () {
    $shop = Shop::factory()->approved()->create();
    $product = Product::factory()->for($shop)->approved()->create();
    $category = ProductCategory::factory()->for($shop)->create();
    $brand = Brand::factory()->for($shop)->create();
    $this->actingAs($shop->owner, 'backpack')->put(route('product.update', $product), [
        'shop_id' => $shop->id, 'name' => $product->name, 'selling_price' => $product->selling_price,
        'category_id' => $category->id, 'brand_id' => $brand->id,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'approved', 'category_id' => $category->id, 'brand_id' => $brand->id]);
});

test('duplication preserves product classifications', function () {
    $shop = Shop::factory()->approved()->create();
    $category = ProductCategory::factory()->for($shop)->create();
    $brand = Brand::factory()->for($shop)->create();
    $product = Product::factory()->for($shop)->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
    $this->actingAs($shop->owner, 'backpack');
    $this->post(route('workspace.duplicate', $product))->assertRedirect();
    $copy = Product::where('id', '!=', $product->id)->firstOrFail();
    expect($copy->category_id)->toBe($category->id);
    expect($copy->brand_id)->toBe($brand->id);
});

test('classification names are unique within a shop and frozen shops cannot change them', function (string $model, string $route) {
    $shop = Shop::factory()->create();
    $entry = $model::factory()->for($shop)->create(['name' => 'Pantry']);
    $this->actingAs($shop->owner, 'backpack')->post(route($route.'.store'), ['shop_id' => $shop->id, 'name' => 'Pantry'])->assertSessionHasErrors('name');
    $shop->forceFill(['status' => 'frozen'])->save();
    $this->post(route($route.'.store'), ['shop_id' => $shop->id, 'name' => 'Another'])->assertForbidden();
    $this->put(route($route.'.update', $entry), ['shop_id' => $shop->id, 'name' => 'Another'])->assertForbidden();
    $this->assertDatabaseCount($entry->getTable(), 1);
})->with([[ProductCategory::class, 'product-category'], [Brand::class, 'brand']]);

test('shop users can quickly create classifications without saving a product', function (string $model, string $kind) {
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->get(route('product.create'))
        ->assertSee('Add a new category')->assertSee('Add a new brand');
    $response = $this->postJson(route('product-classifications.'.$kind), [
        'shop_id' => $shop->id, 'name' => 'Fresh picks', 'description' => "First line\nSecond line",
    ])->assertCreated()->assertJsonPath('name', 'Fresh picks')->assertJsonPath('shop_id', $shop->id);
    $entry = $model::findOrFail($response->json('id'));
    expect(Str::isUuid($entry->id))->toBeTrue();
    expect($entry->description)->toBe("First line\nSecond line");
    $this->assertDatabaseCount('products', 0);

    $this->postJson(route('product-classifications.'.$kind), ['shop_id' => $shop->id, 'name' => 'Fresh picks'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount($entry->getTable(), 1);
})->with([[ProductCategory::class, 'category'], [Brand::class, 'brand']]);

test('quick classification creation rejects unauthorized and frozen shop writes', function (string $kind, string $table) {
    $shop = Shop::factory()->create();
    $payload = ['shop_id' => $shop->id, 'name' => 'Restricted'];
    $this->postJson(route('product-classifications.'.$kind), $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'backpack')->postJson(route('product-classifications.'.$kind), $payload)->assertForbidden();
    $this->flushSession();
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]), 'backpack')->postJson(route('product-classifications.'.$kind), $payload)->assertForbidden();
    $this->flushSession();
    $shop->forceFill(['status' => 'frozen'])->save();
    $this->actingAs($shop->owner, 'backpack')->postJson(route('product-classifications.'.$kind), $payload)->assertForbidden();
    $this->assertDatabaseCount($table, 0);
})->with([['category', 'product_categories'], ['brand', 'brands']]);
