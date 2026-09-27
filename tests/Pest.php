<?php

use App\Models\Branch;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Tenancy\TenantDatabase;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantProvisioner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshTenantDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Seed roles & permissions and create a user with the given role, attached to a branch.
 */
function actingAsRole(string $role = 'owner', ?Branch $branch = null): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $branch ??= Branch::factory()->create(['code' => 'DSM01', 'name' => 'Kariakoo']);
    $user = User::factory()->create(['default_branch_id' => $branch->id]);
    $user->assignRole($role);
    $user->branches()->attach($branch);
    test()->actingAs($user);

    return $user;
}

/**
 * Create another business with its own database. The test business (#1)
 * stays the active one; the new database is dropped after the test.
 */
function provisionTenant(array $data = []): Tenant
{
    $tenant = app(TenantProvisioner::class)->provision($data + [
        'business_name' => 'Second Shop',
        'owner_name' => 'Juma Hamisi',
        'email' => 'juma@second.test',
        'phone' => '255754000111',
        'password' => 'secret-pass-1',
    ]);
    test()->beforeApplicationDestroyed(fn () => app(TenantDatabase::class)->drop($tenant));

    return $tenant;
}

/** The test business (#1). */
function testTenant(): Tenant
{
    return Tenant::findOrFail(1);
}

/** Run a callback inside another business, then return to the test business. */
function inTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantManager::class)->run($tenant, $callback);
}

function actingAsAdmin(bool $super = true): PlatformAdmin
{
    $admin = PlatformAdmin::firstOrCreate(['email' => ($super ? 'super' : 'support').'@platform.test'], ['name' => 'Neema Admin', 'password' => 'admin-password', 'is_super' => $super, 'is_active' => true]);
    test()->actingAs($admin, 'admin');
    // As in a browser: shop pages keep using the "web" guard.
    auth()->shouldUse('web');

    return $admin;
}
