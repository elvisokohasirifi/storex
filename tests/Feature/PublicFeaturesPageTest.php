<?php

use App\Models\Shop;

test('landing page advertises Storex features to potential shop owners', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('features'), false)
        ->assertSee('Features')
        ->assertSee('Features built for small shops')
        ->assertSee('Online storefronts')
        ->assertSee('Inventory and stock control')
        ->assertSee('Point of sale')
        ->assertSee('See all features');
});

test('features page details the public app capabilities', function () {
    $this->get(route('features'))
        ->assertOk()
        ->assertSee('Everything small shops need to sell and stay organized')
        ->assertSee('Online storefronts')
        ->assertSee('Inventory and stock control')
        ->assertSee('Point of sale')
        ->assertSee('Payments')
        ->assertSee('Products, brands, and categories')
        ->assertSee('Finance and tax records')
        ->assertSee('Shop teams and roles')
        ->assertSee('Moderation and logs')
        ->assertSee('Start your shop');
});

test('shop storefront header links back to public features page through platform home', function () {
    $shop = Shop::factory()->approved()->create();

    $this->get(route('shops.show', $shop->slug))
        ->assertOk()
        ->assertSee(route('home'), false);
});
