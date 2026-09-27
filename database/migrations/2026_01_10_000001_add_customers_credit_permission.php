<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Credit limits, wholesale status and opening balances are no longer part
     * of "customers.manage" (cashiers could raise their customers' limits).
     * Granted to existing manager roles without touching other role edits.
     */
    public function up(): void
    {
        if (! Role::query()->exists()) {
            return; // fresh install: RolesAndPermissionsSeeder creates everything
        }
        $permission = Permission::findOrCreate('customers.credit', 'web');
        Role::query()->where('name', 'manager')->get()->each->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'customers.credit')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
