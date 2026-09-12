<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

test('safe shop and product changes are recorded in the activity log without payment credentials', function () {
    $admin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($admin, 'backpack');
    $shop = Shop::factory()->create(['name' => 'Logged Shop', 'momo_number' => '0248260286', 'momo_account_name' => 'Private Wallet']);
    $product = Product::factory()->for($shop)->create(['name' => 'Logged Product']);
    $product->update(['name' => 'Updated Logged Product']);

    $shopActivity = Activity::query()->where('subject_type', Shop::class)->where('subject_id', $shop->id)->firstOrFail();
    $productActivity = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'updated')
        ->firstOrFail();

    expect($shopActivity->causer_id)->toBe($admin->id)
        ->and($shopActivity->log_name)->toBe('moderation')
        ->and($shopActivity->properties->toJson())->not->toContain('0248260286')
        ->and($shopActivity->properties->toJson())->not->toContain('Private Wallet')
        ->and($productActivity->event)->toBe('updated')
        ->and($productActivity->properties->toJson())->toContain('Updated Logged Product');
});

test('log screens enforce platform and store admin access rules', function (string $path) {
    $shopOwner = User::factory()->create(['is_platform_admin' => false]);
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

    $this->actingAs($shopOwner, 'backpack')->get(backpack_url($path))->assertForbidden();

    if ($path === 'activity-log') {
        expect(collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === config('backpack.base.route_prefix').'/activity-log'))->toBeTrue();

        return;
    }

    $this->actingAs($platformAdmin, 'backpack')->get(backpack_url($path))->assertOk();
})->with(['activity-log', 'log']);

test('store admins only see activity logs for their own shops', function () {
    $storeAdmin = User::factory()->create(['is_platform_admin' => false]);
    $ownShop = Shop::factory()->for($storeAdmin, 'owner')->create(['name' => 'Visible Store']);
    $otherShop = Shop::factory()->create(['name' => 'Hidden Store']);
    $ownProduct = Product::factory()->for($ownShop)->create(['name' => 'Visible Product']);
    $otherProduct = Product::factory()->for($otherShop)->create(['name' => 'Hidden Product']);

    $ownProduct->update(['name' => 'Visible Updated Product']);
    $otherProduct->update(['name' => 'Hidden Updated Product']);

    $response = $this->actingAs($storeAdmin, 'backpack')
        ->postJson(backpack_url('activity-log/search'), ['start' => 0, 'length' => 25]);

    $response
        ->assertOk()
        ->assertSee('Visible Updated Product')
        ->assertDontSee('Hidden Updated Product');
});

test('store admins cannot open activity log records from other shops directly', function () {
    $storeAdmin = User::factory()->create(['is_platform_admin' => false]);
    $ownShop = Shop::factory()->for($storeAdmin, 'owner')->create();
    $otherShop = Shop::factory()->create();
    Product::factory()->for($ownShop)->create();
    $otherProduct = Product::factory()->for($otherShop)->create(['name' => 'Other Direct Product']);
    $otherProduct->update(['name' => 'Other Direct Updated Product']);
    $otherActivity = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $otherProduct->id)
        ->where('event', 'updated')
        ->firstOrFail();

    $this->actingAs($storeAdmin, 'backpack')
        ->get(backpack_url("activity-log/{$otherActivity->id}/show"))
        ->assertNotFound();
});

test('platform admins see activity logs for every shop', function () {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
    $firstProduct = Product::factory()->create(['name' => 'First Store Product']);
    $secondProduct = Product::factory()->create(['name' => 'Second Store Product']);

    $firstProduct->update(['name' => 'First Updated Product']);
    $secondProduct->update(['name' => 'Second Updated Product']);

    $response = $this->actingAs($platformAdmin, 'backpack')
        ->postJson(backpack_url('activity-log/search'), ['start' => 0, 'length' => 25]);

    $response
        ->assertOk()
        ->assertSee('First Updated Product')
        ->assertSee('Second Updated Product');
});

test('store managers cannot view activity logs', function () {
    $manager = User::factory()->create(['is_platform_admin' => false]);
    $shop = Shop::factory()->create();
    $shop->members()->create(['user_id' => $manager->id, 'role' => 'manager']);

    $this->actingAs($manager, 'backpack')
        ->postJson(backpack_url('activity-log/search'), ['start' => 0, 'length' => 25])
        ->assertForbidden();
});

test('activity logs menu is visible to shop admins as the last item before help', function () {
    $shop = Shop::factory()->create();

    $content = $this->actingAs($shop->owner, 'backpack')
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertSee('Activity logs')
        ->assertSee('Help')
        ->content();

    expect(strpos($content, 'Activity logs'))->toBeLessThan(strpos($content, 'Help'));
});

test('activity logs menu is hidden from store managers', function () {
    $manager = User::factory()->create(['is_platform_admin' => false]);
    $shop = Shop::factory()->create();
    $shop->members()->create(['user_id' => $manager->id, 'role' => 'manager']);

    $this->actingAs($manager, 'backpack')
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertDontSee('Activity logs')
        ->assertSee('Help');
});

test('mail logging uses a dedicated Laravel log channel', function () {
    expect(Config::get('mail.mailers.log.channel'))->toBe('mail')
        ->and(Config::get('logging.channels.mail.driver'))->toBe('daily')
        ->and(Config::get('logging.channels.mail.path'))->toBe(storage_path('logs/mail.log'));
});
