<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\GiftCardService;
use App\Services\ProductService;
use App\Services\PromotionService;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Demo promotions, a bundle and gift cards. Runs after the sales history so
 * past demo sales are not affected by today's promotions.
 */
class SellingSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::role('owner')->first();
        $manager = User::role('manager')->first() ?? $owner;
        if (! $owner) {
            return;
        }
        app(SettingsService::class)->set(['features.bundles' => true, 'features.gift_cards' => true]);
        $branchId = $manager->default_branch_id ?? $manager->branches()->value('branches.id');

        $promotions = app(PromotionService::class);
        $softDrinks = Category::where('name', 'Soft Drinks')->value('id');
        if ($softDrinks) {
            $promotions->save(['name' => 'Soda weekend', 'type' => 'buy_get', 'buy_qty' => 5, 'get_qty' => 1, 'applies_to' => 'categories',
                'category_ids' => [$softDrinks], 'days_of_week' => [5, 6, 7], 'is_active' => true], $manager);
        }
        $juice = Product::where('name', 'Azam Mango Juice 300ml')->value('id');
        if ($juice) {
            $promotions->save(['name' => 'Juice happy hour', 'type' => 'percent', 'value' => 15, 'applies_to' => 'products',
                'product_ids' => [$juice], 'start_time' => '16:00', 'end_time' => '18:00', 'is_active' => true], $manager);
        }
        $water = Product::where('name', 'Azam Maji 500ml')->value('id');
        if ($water) {
            $promotions->save(['name' => 'Maji 3 kwa 1,300', 'type' => 'multi_price', 'buy_qty' => 3, 'value' => 1300, 'applies_to' => 'products',
                'product_ids' => [$water], 'is_active' => true], $manager);
        }

        $items = Product::whereIn('name', ['Sukari 1kg', 'Mafuta ya Kupikia Korie 1L', 'Unga wa Sembe 2kg'])->get();
        if ($items->count() === 3) {
            app(ProductService::class)->create([
                'name' => 'Kifurushi cha Jikoni', 'unit_id' => $items->first()->unit_id, 'tax_type' => 'standard',
                'retail_price' => (int) round($items->sum('retail_price') * 0.95 / 100) * 100,
                'is_bundle' => true, 'is_active' => true, 'description' => 'Sukari, mafuta na unga kwa bei ya pamoja.',
                'bundle_items' => $items->map(fn ($p) => ['component_id' => $p->id, 'quantity' => 1])->all(),
            ], $owner);
        }

        if ($branchId) {
            $cards = app(GiftCardService::class);
            $cards->issue(['value' => 50000, 'kind' => 'voucher', 'note' => 'Zawadi ya mteja bora', 'expires_on' => now()->addMonths(3)->toDateString()], $manager, $branchId);
            $cards->issue(['value' => 20000, 'kind' => 'voucher', 'note' => 'Promo ya ufunguzi'], $manager, $branchId);
        }
    }
}
