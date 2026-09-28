<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\ProductService;
use App\Services\SerialService;
use App\Services\SettingsService;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/** Demo phone with IMEIs, medicines (one prescription-only), a supplier link and a post-dated cheque. */
class ShopTypesSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::role('owner')->first();
        $manager = User::role('manager')->first() ?? $owner;
        $branchId = $manager?->default_branch_id ?? $manager?->branches()->value('branches.id');
        $unit = Unit::where('short_name', 'pc')->first() ?? Unit::first();
        if (! $owner || ! $branchId || ! $unit) {
            return;
        }
        app(SettingsService::class)->set(['features.serials' => true, 'features.pharmacy' => true, 'features.cheques' => true]);
        $products = app(ProductService::class);
        $stock = app(StockService::class);

        $phones = Category::firstOrCreate(['name' => 'Simu'], ['is_active' => true]);
        $phone = $products->create(['name' => 'Tecno Spark 20 128GB', 'unit_id' => $unit->id, 'category_id' => $phones->id, 'tax_type' => 'standard',
            'cost_price' => 285000, 'retail_price' => 349000, 'track_stock' => true, 'track_serials' => true, 'warranty_months' => 12, 'is_active' => true], $owner);
        $imeis = ['356938035643809', '356938035643817', '356938035643825', '356938035643833'];
        $stock->receive($branchId, $phone, count($imeis), MovementType::Opening);
        app(SerialService::class)->register($phone, $branchId, $imeis, $owner);

        $medicine = Category::firstOrCreate(['name' => 'Dawa'], ['is_active' => true]);
        foreach ([
            ['Panadol 500mg (strip 10)', 'Paracetamol', '500mg', 'tablet', false, 800, 1200],
            ['Amoxil 500mg (strip 10)', 'Amoxicillin', '500mg', 'capsule', true, 2500, 3500],
        ] as [$name, $generic, $strength, $form, $rx, $cost, $price]) {
            $product = $products->create(['name' => $name, 'generic_name' => $generic, 'strength' => $strength, 'dosage_form' => $form, 'requires_prescription' => $rx,
                'unit_id' => $unit->id, 'category_id' => $medicine->id, 'tax_type' => 'exempt', 'cost_price' => $cost, 'retail_price' => $price,
                'track_stock' => true, 'is_active' => true], $owner);
            $stock->receive($branchId, $product, 50, MovementType::Opening);
        }

        if ($supplier = Supplier::query()->orderBy('id')->first()) {
            ProductSupplier::updateOrCreate(['product_id' => $phone->id, 'supplier_id' => $supplier->id],
                ['supplier_sku' => 'TEC-SP20-128', 'last_cost' => 285000, 'lead_time_days' => 7, 'is_preferred' => true]);
        }

        $customer = Customer::query()->where('balance', '>', 20000)->orderByDesc('balance')->first();
        if ($customer) {
            app(CustomerPaymentService::class)->receive($customer, 20000, PaymentMethod::Cheque, $manager, $branchId, '004512', __('Demo post-dated cheque'), null,
                ['bank' => 'CRDB Bank', 'cheque_date' => now()->addDays(14)->toDateString()]);
        }
    }
}
