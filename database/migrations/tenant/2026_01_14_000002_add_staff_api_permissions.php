<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Sales targets (managers) and API tokens (owner only by default). */
    public function up(): void
    {
        if (! Role::query()->exists()) {
            return; // fresh install: RolesAndPermissionsSeeder creates everything
        }
        $targets = Permission::findOrCreate('targets.manage', 'web');
        Permission::findOrCreate('api.tokens', 'web');
        Role::query()->where('name', 'manager')->get()->each->givePermissionTo($targets);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['targets.manage', 'api.tokens'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
