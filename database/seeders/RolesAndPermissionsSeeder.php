<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(config('dukapos.permissions'))->flatMap(fn ($perms) => array_keys($perms));
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('dukapos.roles') as $roleName => $definition) {
            $role = Role::findOrCreate($roleName, 'web');
            $patterns = $definition['permissions'];
            $granted = $all->filter(fn ($perm) => collect($patterns)->contains(fn ($p) => $p === '*' || $p === $perm || Str::is($p, $perm)));
            $role->syncPermissions($granted->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
