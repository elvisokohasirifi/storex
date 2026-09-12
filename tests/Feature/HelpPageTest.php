<?php

use App\Models\Shop;
use App\Models\User;

test('platform admins see moderation help without shop finance guidance', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin, 'backpack')
        ->get(route('help'))
        ->assertOk()
        ->assertSee('Role: App admin')
        ->assertSee('Moderation overview')
        ->assertSee('Activity logs')
        ->assertSee('Error logs')
        ->assertDontSee('Income, expenses, finance, and tax records')
        ->assertDontSee('Payment credentials');
});

test('shop admins see setup, finance, inventory, payment, and activity help', function () {
    $shop = Shop::factory()->create();

    $this->actingAs($shop->owner, 'backpack')
        ->get(route('help'))
        ->assertOk()
        ->assertSee('Role: Shop admin')
        ->assertSee('Users and payment credentials')
        ->assertSee('Inventory management')
        ->assertSee('Discounts')
        ->assertSee('Income, expenses, finance, and tax records')
        ->assertSee('Shop admins can see activity logs that relate to their own shops')
        ->assertDontSee('Error logs');
});

test('store managers see day to day help without admin-only setup guidance', function () {
    $manager = User::factory()->create(['is_platform_admin' => false]);
    $shop = Shop::factory()->create();
    $shop->members()->create(['user_id' => $manager->id, 'role' => 'manager']);

    $this->actingAs($manager, 'backpack')
        ->get(route('help'))
        ->assertOk()
        ->assertSee('Role: Store manager')
        ->assertSee('Products, categories, and brands')
        ->assertSee('Till, sales, receipts, and manual payments')
        ->assertSee('Income, expenses, finance, and tax records')
        ->assertDontSee('Users and payment credentials')
        ->assertDontSee('Inventory management page')
        ->assertDontSee('Shop admins can see activity logs that relate to their own shops')
        ->assertDontSee('Error logs');
});
