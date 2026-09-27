<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Realistic Tanzanian demo data across all modules.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        activity()->disableLogging();

        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
        ]);

        foreach (['CatalogSeeder', 'InventorySeeder', 'CustomerSeeder', 'SupplierSeeder', 'PurchaseSeeder', 'SalesSeeder', 'ExpenseSeeder'] as $seeder) {
            $class = __NAMESPACE__.'\\'.$seeder;
            if (class_exists($class)) {
                $this->call($class);
            }
        }

        activity()->enableLogging();
    }
}
