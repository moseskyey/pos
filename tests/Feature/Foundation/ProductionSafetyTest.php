<?php

use App\Models\User;
use App\Support\Environment;
use Illuminate\Support\Facades\Route;

/*
 * Regression for the bug-scan report #2: the live site ran with APP_DEBUG=true
 * and a crafted URL returned a full stack trace.
 */
beforeEach(function () {
    Route::get('/_boom', fn () => throw new RuntimeException('secret-internal-detail'))->middleware('web');
    config(['app.debug' => true]);
});

it('never shows stack traces on a public address even with APP_DEBUG=true', function () {
    $public = $this->get('https://pos.example.co.tz/_boom');
    $public->assertStatus(500)->assertDontSee('secret-internal-detail')->assertDontSee('vendor/laravel');

    config(['app.debug' => true]);
    $this->get('http://localhost/_boom')->assertStatus(500)->assertSee('secret-internal-detail'); // still available while developing
});

it('handles array input on the reset-password page', function () {
    $this->get('https://pos.example.co.tz/reset-password/x?email[]=a')->assertOk();
    $this->get('/reset-password/tok?email=a@b.test')->assertOk()->assertSee('a@b.test', false);
});

it('warns the owner when the server runs in development mode', function () {
    expect(Environment::warnings('localhost'))->toBe([])
        ->and(Environment::warnings('pos.example.co.tz'))->not->toBe([]);

    actingAsRole('owner');
    $this->get('https://pos.example.co.tz/dashboard')->assertSee(__('Server configuration needs attention:'));
    $this->actingAs(tap(User::factory()->create())->assignRole('cashier'))
        ->get('https://pos.example.co.tz/dashboard')->assertDontSee(__('Server configuration needs attention:'));
});
