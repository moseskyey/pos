<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Promotions and gift cards: new permissions, granted to existing manager roles. */
    public function up(): void
    {
        if (! Role::query()->exists()) {
            return; // fresh install: RolesAndPermissionsSeeder creates everything
        }
        foreach (['promotions.manage', 'gift_cards.manage'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            Role::query()->where('name', 'manager')->get()->each->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['promotions.manage', 'gift_cards.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
