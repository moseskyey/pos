<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe base data: roles, permissions and a first branch/owner.
     * Run `php artisan db:seed --class=DemoSeeder` for full demo data.
     */
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
