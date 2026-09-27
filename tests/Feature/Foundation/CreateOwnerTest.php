<?php

use App\Models\Branch;
use App\Models\User;

it('creates the owner and first branch on a fresh install', function () {
    $this->artisan('dukapos:create-owner', [
        '--name' => 'Asha Mushi', '--email' => 'asha@example.co.tz', '--password' => 'secret123',
        '--branch' => 'Kariakoo', '--code' => 'dsm01',
    ])->assertSuccessful();

    $user = User::where('email', 'asha@example.co.tz')->firstOrFail();
    expect($user->hasRole('owner'))->toBeTrue()
        ->and($user->defaultBranch->code)->toBe('DSM01')
        ->and(Branch::first()->registers()->count())->toBe(1);
});

it('rejects invalid input', function () {
    $this->artisan('dukapos:create-owner', [
        '--name' => 'X', '--email' => 'not-an-email', '--password' => 'short', '--branch' => 'Main', '--code' => 'MAIN',
    ])->assertFailed();
    expect(User::count())->toBe(0);
});
