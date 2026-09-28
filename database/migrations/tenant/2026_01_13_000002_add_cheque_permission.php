<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Cheque tracking: new permission for existing manager and accountant roles. */
    public function up(): void
    {
        if (! Role::query()->exists()) {
            return; // fresh install: RolesAndPermissionsSeeder creates everything
        }
        $permission = Permission::findOrCreate('cheques.manage', 'web');
        Role::query()->whereIn('name', ['manager', 'accountant'])->get()->each->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'cheques.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
