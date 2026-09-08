<?php

use App\Models\LedgerEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    Http::preventStrayRequests();
});

test('registration supports email password and phone PIN without granting admin access', function () {
    $this->post(route('backpack.auth.register'), ['name' => 'Owner', 'email' => 'owner@example.com',
        'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123',
        'phone' => '+233241234567', 'pin' => '012345', 'pin_confirmation' => '012345', 'is_platform_admin' => true,
    ])->assertRedirect(route('shop.create'));
    $user = User::firstOrFail();
    expect($user->is_platform_admin)->toBeFalse();
    expect(Hash::check('012345', $user->pin))->toBeTrue();
    $this->assertAuthenticatedAs($user, 'backpack');
});

test('both email password and phone PIN authenticate the correct user', function (string $method) {
    $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'secure-password-123', 'phone' => '+233241234567', 'pin' => '012345']);
    $payload = $method === 'email' ? ['method' => 'email', 'email' => $user->email, 'password' => 'secure-password-123'] : ['method' => 'phone', 'phone' => $user->phone, 'pin' => '012345'];
    $this->post(route('backpack.auth.login'), $payload)->assertRedirect(route('workspace.index'));
    $this->assertAuthenticatedAs($user, 'backpack');
})->with(['email', 'phone']);

test('PIN attempts are locked after five failures and never flashed into session', function () {
    $user = User::factory()->create(['phone' => '+233241234567', 'pin' => '012345']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('backpack.auth.login'), ['method' => 'phone', 'phone' => $user->phone, 'pin' => '654321'])->assertSessionHasErrors('login');
    }
    $this->post(route('backpack.auth.login'), ['method' => 'phone', 'phone' => $user->phone, 'pin' => '012345'])->assertSessionHasErrors(['login' => 'Too many attempts. Try again in 300 seconds.']);
    expect(session()->getOldInput('pin'))->toBeNull();
    $this->assertGuest('backpack');
});

test('Google callback rejects a forged state without calling Google', function () {
    $this->get(route('google.callback', ['state' => 'forged', 'code' => 'fake']))->assertSessionHasErrors('google');
    Http::assertNothingSent();
    $this->assertGuest('backpack');
});

test('Google signs in a verified new account using the server flow', function () {
    config(['services.google.client_id' => 'client', 'services.google.client_secret' => 'secret']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'google-123', 'email' => 'google@example.com', 'email_verified' => true, 'name' => 'Google Owner']),
    ]);
    $this->get(route('google.redirect'))->assertRedirect();
    $state = session('google_oauth.state');
    $this->get(route('google.callback', ['state' => $state, 'code' => 'valid-code']))->assertRedirect(route('workspace.index'));
    $this->assertAuthenticatedAs(User::where('google_id', 'google-123')->firstOrFail(), 'backpack');
    Http::assertSentCount(2);
});

test('Google cannot silently link an existing email account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    config(['services.google.client_id' => 'client', 'services.google.client_secret' => 'secret']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'access']), 'openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'attacker', 'email' => $user->email, 'email_verified' => true])]);
    $this->get(route('google.redirect'));
    $this->get(route('google.callback', ['state' => session('google_oauth.state'), 'code' => 'valid-code']))->assertSessionHasErrors('google');
    expect($user->fresh()->google_id)->toBeNull();
    $this->assertGuest('backpack');
});

test('expenses store receipts privately and prevent other shops downloading them', function () {
    Storage::fake('local');
    $shop = Shop::factory()->create();
    $this->actingAs($shop->owner, 'backpack')->post(route('ledger-entry.store'), [
        'shop_id' => $shop->id, 'type' => 'expense', 'category' => 'Utilities', 'description' => 'Electricity',
        'amount' => '45.00', 'occurred_on' => now()->toDateString(), 'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $entry = LedgerEntry::firstOrFail();
    Storage::disk('local')->assertExists(Str::start($entry->receipt, 'receipts/'));
    $this->get(route('finance.receipt', $entry))->assertDownload();
    $this->actingAs(User::factory()->create(), 'backpack')->get(route('finance.receipt', $entry))->assertNotFound();
});

test('finance export includes selected dates and escapes spreadsheet formulas', function () {
    $shop = Shop::factory()->create();
    LedgerEntry::factory()->for($shop)->create(['description' => '=BAD()', 'occurred_on' => '2026-09-01', 'amount' => '45.00']);
    LedgerEntry::factory()->for($shop)->create(['description' => 'Old item', 'occurred_on' => '2025-01-01']);
    $response = $this->actingAs($shop->owner, 'backpack')->get(route('finance.index', ['shop_id' => $shop->id, 'from' => '2026-09-01', 'to' => '2026-09-07', 'export' => 1]));
    $response->assertDownload();
    expect($response->streamedContent())->toContain("'=BAD()")->toContain('45.00')->not->toContain('Old item');
});

test('phone setup requires the current password and six digits', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'backpack')->post(route('account.security.update'), ['password' => 'wrong', 'phone' => '+233241234567', 'pin' => '123456', 'pin_confirmation' => '123456'])->assertSessionHasErrors('password');
    expect($user->fresh()->phone)->toBeNull();
});

test('authentication and finance pages render', function () {
    $this->get(route('backpack.auth.login'))->assertOk();
    $this->get(route('backpack.auth.register'))->assertOk();
    $shop = Shop::factory()->create();
    LedgerEntry::factory()->for($shop)->create();
    $this->actingAs($shop->owner, 'backpack')->get(route('finance.index'))->assertOk()->assertSee('45.00');
});
