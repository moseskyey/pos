<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Services\ProductService;
use App\Support\BarcodeParser;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [];
        foreach ([
            ['Piece', 'pc', false], ['Dozen', 'dz', false], ['Carton', 'ctn', false], ['Pack', 'pkt', false],
            ['Kilogram', 'kg', true], ['Litre', 'L', true], ['Metre', 'm', true], ['Bag', 'bag', false], ['Crate', 'crt', false],
        ] as [$name, $short, $decimal]) {
            $units[$short] = Unit::updateOrCreate(['short_name' => $short], ['name' => $name, 'allow_decimal' => $decimal, 'is_active' => true]);
        }
        foreach ([['dz', 'pc', 12], ['ctn', 'pc', 24], ['crt', 'pc', 24], ['pkt', 'pc', 10]] as [$from, $to, $factor]) {
            UnitConversion::updateOrCreate(['from_unit_id' => $units[$from]->id, 'to_unit_id' => $units[$to]->id], ['factor' => $factor]);
        }

        $tree = [
            'Beverages' => ['#0EA5E9', ['Water', 'Soft Drinks', 'Juices', 'Energy Drinks']],
            'Groceries' => ['#16A34A', ['Flour', 'Sugar', 'Rice', 'Cooking Oil', 'Spreads']],
            'Dairy' => ['#F59E0B', ['Milk', 'Yoghurt']],
            'Household' => ['#8B5CF6', ['Cleaning', 'Laundry']],
            'Personal Care' => ['#EC4899', ['Oral Care', 'Skin Care', 'Hair Care']],
            'Pharmacy' => ['#DC2626', ['Pain Relief', 'Vitamins']],
            'Hardware' => ['#64748B', ['Building', 'Tools']],
            'Electronics' => ['#111827', ['Phones', 'Accessories']],
            'Boutique' => ['#14B8A6', ['Clothing']],
        ];
        $cats = [];
        $order = 0;
        foreach ($tree as $parentName => [$color, $children]) {
            $parent = Category::firstOrCreate(['name' => $parentName, 'parent_id' => null], ['color' => $color, 'sort_order' => $order++, 'is_active' => true]);
            $cats[$parentName] = $parent;
            foreach ($children as $i => $child) {
                $cats[$child] = Category::firstOrCreate(['name' => $child, 'parent_id' => $parent->id], ['color' => $color, 'sort_order' => $i, 'is_active' => true]);
            }
        }

        $brandNames = ['Azam', 'Kilimanjaro', 'Coca-Cola', 'Pepsi', 'Bakhresa', 'Korie', 'Blueband', 'Mo Energy', 'Omo', 'Colgate', 'Nivea',
            'Tanga Fresh', 'Dabaga', 'Panadol', 'Simba Cement', 'Tecno', 'Itel', 'Kiboko', 'Jik', 'Motisun', 'Sayona', 'Afya', 'Dasani', 'Pride Rice', 'Kilombero', 'DukaWear'];
        $brands = [];
        foreach ($brandNames as $name) {
            $brands[$name] = Brand::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // name, category, brand, unit, cost, retail, wholesale, min qty, tax, reorder, carton factor, track_batches
        $products = [
            ['Azam Maji 500ml', 'Water', 'Azam', 'pc', 350, 500, 450, 24, 'standard', 48, 24, false],
            ['Azam Maji 1.5L', 'Water', 'Azam', 'pc', 750, 1000, 900, 12, 'standard', 24, 12, false],
            ['Kilimanjaro Water 1L', 'Water', 'Kilimanjaro', 'pc', 600, 800, 750, 12, 'standard', 24, 12, false],
            ['Kilimanjaro Water 500ml', 'Water', 'Kilimanjaro', 'pc', 380, 500, 460, 24, 'standard', 48, 24, false],
            ['Dasani 500ml', 'Water', 'Dasani', 'pc', 400, 600, 550, 24, 'standard', 24, 24, false],
            ['Coca-Cola 350ml Bottle', 'Soft Drinks', 'Coca-Cola', 'pc', 450, 600, 550, 24, 'standard', 48, 24, false],
            ['Coca-Cola 1.25L', 'Soft Drinks', 'Coca-Cola', 'pc', 1400, 1800, 1700, 12, 'standard', 12, 12, false],
            ['Fanta Orange 350ml', 'Soft Drinks', 'Coca-Cola', 'pc', 450, 600, 550, 24, 'standard', 48, 24, false],
            ['Sprite 350ml', 'Soft Drinks', 'Coca-Cola', 'pc', 450, 600, 550, 24, 'standard', 24, 24, false],
            ['Pepsi 500ml', 'Soft Drinks', 'Pepsi', 'pc', 550, 700, 650, 24, 'standard', 24, 24, false],
            ['Mirinda Fruity 500ml', 'Soft Drinks', 'Pepsi', 'pc', 550, 700, 650, 24, 'standard', 24, 24, false],
            ['Azam Embe Juice 1L', 'Juices', 'Azam', 'pc', 2000, 2500, 2300, 12, 'standard', 12, 12, false],
            ['Azam Mango Juice 300ml', 'Juices', 'Azam', 'pc', 600, 800, 750, 24, 'standard', 24, 24, false],
            ['Mo Energy 300ml', 'Energy Drinks', 'Mo Energy', 'pc', 700, 1000, 900, 24, 'standard', 24, 24, false],
            ['Unga wa Sembe 2kg', 'Flour', 'Azam', 'pc', 3600, 4200, 4000, 10, 'exempt', 20, 10, false],
            ['Unga wa Sembe 5kg', 'Flour', 'Azam', 'pc', 8800, 10000, 9600, 5, 'exempt', 10, null, false],
            ['Unga wa Ngano Azam 2kg', 'Flour', 'Azam', 'pc', 4200, 5000, 4700, 10, 'exempt', 10, 10, false],
            ['Sukari 1kg', 'Sugar', 'Kilombero', 'pc', 2700, 3200, 3000, 10, 'standard', 30, null, false],
            ['Sukari 2kg', 'Sugar', 'Kilombero', 'pc', 5400, 6300, 6000, 10, 'standard', 15, null, false],
            ['Sukari (loose)', 'Sugar', 'Kilombero', 'kg', 2600, 3000, 2900, 25, 'standard', 50, null, false],
            ['Mchele Pride 5kg', 'Rice', 'Pride Rice', 'pc', 12500, 15000, 14200, 4, 'exempt', 10, null, false],
            ['Mchele (loose)', 'Rice', 'Pride Rice', 'kg', 2400, 3000, 2800, 25, 'exempt', 50, null, false],
            ['Mafuta ya Kupikia Korie 1L', 'Cooking Oil', 'Korie', 'pc', 5200, 6000, 5700, 12, 'standard', 24, 12, false],
            ['Mafuta ya Kupikia Korie 3L', 'Cooking Oil', 'Korie', 'pc', 15000, 17500, 16800, 6, 'standard', 10, 6, false],
            ['Mafuta ya Alizeti Sayona 1L', 'Cooking Oil', 'Sayona', 'pc', 5800, 6800, 6400, 12, 'standard', 12, 12, false],
            ['Blueband 250g', 'Spreads', 'Blueband', 'pc', 2300, 2800, 2600, 12, 'standard', 24, 24, false],
            ['Blueband 500g', 'Spreads', 'Blueband', 'pc', 4300, 5200, 4900, 12, 'standard', 12, 12, false],
            ['Tanga Fresh Maziwa 500ml', 'Milk', 'Tanga Fresh', 'pc', 1100, 1500, 1400, 12, 'exempt', 24, 12, true],
            ['Tanga Fresh Mtindi 500ml', 'Yoghurt', 'Tanga Fresh', 'pc', 1400, 1800, 1700, 12, 'standard', 12, 12, true],
            ['Dabaga Tomato Sauce 400g', 'Spreads', 'Dabaga', 'pc', 2200, 2800, 2600, 12, 'standard', 12, 12, true],
            ['Omo Sabuni ya Unga 1kg', 'Laundry', 'Omo', 'pc', 4800, 5800, 5500, 12, 'standard', 12, 12, false],
            ['Omo 500g', 'Laundry', 'Omo', 'pc', 2500, 3000, 2800, 24, 'standard', 24, 24, false],
            ['Kiboko Sabuni ya Mche', 'Laundry', 'Kiboko', 'pc', 1500, 2000, 1800, 12, 'standard', 24, 24, false],
            ['Jik Bleach 750ml', 'Cleaning', 'Jik', 'pc', 2600, 3200, 3000, 12, 'standard', 12, 12, false],
            ['Colgate Toothpaste 100ml', 'Oral Care', 'Colgate', 'pc', 2800, 3500, 3200, 12, 'standard', 12, 12, false],
            ['Colgate Toothbrush', 'Oral Care', 'Colgate', 'pc', 900, 1500, 1300, 12, 'standard', 12, 12, false],
            ['Nivea Body Lotion 400ml', 'Skin Care', 'Nivea', 'pc', 11000, 14000, 13000, 6, 'standard', 6, null, false],
            ['Nivea Roll-on Men 50ml', 'Skin Care', 'Nivea', 'pc', 6500, 8500, 8000, 6, 'standard', 6, null, false],
            ['Motisun Vaseline 250ml', 'Skin Care', 'Motisun', 'pc', 3000, 4000, 3700, 12, 'standard', 12, 12, false],
            ['Panadol Extra (strip)', 'Pain Relief', 'Panadol', 'pkt', 1200, 2000, 1800, 10, 'exempt', 20, null, true],
            ['Paracetamol 500mg (strip)', 'Pain Relief', 'Afya', 'pkt', 300, 500, 450, 10, 'exempt', 30, null, true],
            ['Vitamin C 1000mg (tube)', 'Vitamins', 'Afya', 'pc', 4500, 6500, 6000, 6, 'exempt', 6, null, true],
            ['Saruji Simba 50kg', 'Building', 'Simba Cement', 'bag', 15500, 17500, 17000, 20, 'standard', 20, null, false],
            ['Nondo 12mm (6m)', 'Building', null, 'pc', 19000, 23000, 22000, 20, 'standard', 20, null, false],
            ['Misumari 3" (kg)', 'Tools', null, 'kg', 3800, 5000, 4600, 10, 'standard', 10, null, false],
            ['Tecno Spark 20', 'Phones', 'Tecno', 'pc', 290000, 340000, 330000, 3, 'standard', 3, null, false],
            ['Itel A70', 'Phones', 'Itel', 'pc', 190000, 225000, 218000, 3, 'standard', 3, null, false],
            ['USB-C Charger 2A', 'Accessories', 'Itel', 'pc', 5500, 9000, 8000, 10, 'standard', 10, null, false],
            ['Earphones Wired', 'Accessories', 'Itel', 'pc', 2500, 5000, 4500, 10, 'standard', 10, null, false],
        ];

        $service = app(ProductService::class);
        $owner = User::where('email', 'owner@dukapos.test')->first();
        $n = 1;
        foreach ($products as [$name, $cat, $brand, $unit, $cost, $retail, $wholesale, $minQty, $tax, $reorder, $cartonFactor, $batches]) {
            if (Product::where('name', $name)->exists()) {
                continue;
            }
            $data = [
                'name' => $name,
                'category_id' => $cats[$cat]->id,
                'brand_id' => $brand ? $brands[$brand]->id : null,
                'unit_id' => $units[$unit]->id,
                'cost_price' => $cost,
                'retail_price' => $retail,
                'wholesale_price' => $wholesale,
                'wholesale_min_qty' => $minQty,
                'tax_type' => $tax,
                'reorder_level' => $reorder,
                'track_stock' => true,
                'track_batches' => $batches,
                'is_weighted' => in_array($unit, ['kg'], true),
                'is_active' => true,
                'barcodes' => [BarcodeParser::ean13('620'.str_pad((string) $n, 9, '0', STR_PAD_LEFT))],
                'units' => $cartonFactor ? [[
                    'unit_id' => $units['ctn']->id,
                    'factor' => $cartonFactor,
                    'retail_price' => $wholesale * $cartonFactor,
                    'wholesale_price' => ($wholesale - round($wholesale * .03)) * $cartonFactor,
                    'barcode' => BarcodeParser::ean13('621'.str_pad((string) $n, 9, '0', STR_PAD_LEFT)),
                ]] : [],
            ];
            $service->create($data, $owner);
            $n++;
        }

        // A boutique product with variants.
        if (! Product::where('name', 'DukaWear Cotton T-Shirt')->exists()) {
            $variants = [];
            foreach (['S', 'M', 'L', 'XL'] as $size) {
                foreach (['Black', 'White'] as $colour) {
                    $variants[] = ['attributes' => ['Size' => $size, 'Colour' => $colour], 'retail_price' => $size === 'XL' ? 16000 : 15000, 'cost_price' => 8000,
                        'barcode' => BarcodeParser::ean13('622'.str_pad((string) $n++, 9, '0', STR_PAD_LEFT))];
                }
            }
            $service->create([
                'name' => 'DukaWear Cotton T-Shirt', 'category_id' => $cats['Clothing']->id, 'brand_id' => $brands['DukaWear']->id,
                'unit_id' => $units['pc']->id, 'cost_price' => 8000, 'retail_price' => 15000, 'tax_type' => 'standard', 'reorder_level' => 3,
                'track_stock' => true, 'is_active' => true, 'has_variants' => true, 'variants' => $variants,
            ], $owner);
        }
    }
}
