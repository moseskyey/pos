<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One business's base data: roles and permissions, plus the full demo data
 * outside production. Runs inside a business database, e.g.
 *   php artisan tenants:run "db:seed --class=TenantDatabaseSeeder --force" --tenant=3
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        if (app()->environment(['local', 'testing', 'demo']) || config('app.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
