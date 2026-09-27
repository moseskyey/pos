<?php

namespace Database\Seeders;

use App\Models\Platform\Plan;
use Illuminate\Database\Seeder;

/** Starter subscription plans (editable under Admin → Plans). */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'starter', 'name' => 'Starter', 'description' => 'One shop, one till: everything you need to start selling.', 'price' => 25000, 'interval_months' => 1, 'max_branches' => 1, 'max_users' => 3, 'max_products' => 1000, 'sort_order' => 1],
            ['slug' => 'business', 'name' => 'Business', 'description' => 'Growing shops with a few branches and a bigger team.', 'price' => 60000, 'interval_months' => 1, 'max_branches' => 3, 'max_users' => 15, 'max_products' => 10000, 'sort_order' => 2],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'description' => 'Supermarkets and wholesalers: no limits.', 'price' => 150000, 'interval_months' => 1, 'max_branches' => null, 'max_users' => null, 'max_products' => null, 'sort_order' => 3],
            ['slug' => 'business-yearly', 'name' => 'Business (yearly)', 'description' => 'The Business plan, billed yearly: two months free.', 'price' => 600000, 'interval_months' => 12, 'max_branches' => 3, 'max_users' => 15, 'max_products' => 10000, 'sort_order' => 4],
        ];
        foreach ($plans as $plan) {
            Plan::firstOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }
    }
}
