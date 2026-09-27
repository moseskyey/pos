<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
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
