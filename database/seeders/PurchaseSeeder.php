<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SupplierPaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@dukapos.test')->first();
        $store = User::where('email', 'store@dukapos.test')->first();
        Auth::login($owner);
        $today = now()->startOfDay();
        $purchases = app(PurchaseService::class);
        $payments = app(SupplierPaymentService::class);

        $map = [
            'Bakhresa Group Ltd (Azam)' => ['Azam', 'Bakhresa'],
            'Kilimanjaro Water Co.' => ['Kilimanjaro'],
            'Coca-Cola Kwanza Ltd' => ['Coca-Cola', 'Dasani'],
            'Murzah Wilmar (Korie)' => ['Korie', 'Sayona'],
            'Unilever Tanzania' => ['Blueband', 'Omo', 'Colgate', 'Nivea'],
            'Tanga Fresh Ltd' => ['Tanga Fresh'],
            'Mansoor Pharma Distributors' => ['Panadol', 'Afya'],
            'Simu Direct Electronics' => ['Tecno', 'Itel'],
        ];

        foreach (Branch::orderBy('id')->get() as $branch) {
            foreach ($map as $supplierName => $brands) {
                $supplier = Supplier::where('name', $supplierName)->first();
                $products = Product::query()->sellable()->whereHas('brand', fn ($q) => $q->whereIn('name', $brands))->get();
                if ($products->isEmpty()) {
                    continue;
                }

                // Received order ~25 days ago.
                Carbon::setTestNow($today->copy()->subDays(25)->setTime(9, 0));
                $items = $products->map(fn ($p) => ['product_id' => $p->id, 'quantity' => max(12, (int) $p->reorder_level * 2), 'unit_cost' => $p->cost_price])->all();
                $order = $purchases->saveOrder($branch->id, $supplier, $items, $owner, ['expected_date' => $today->copy()->subDays(23)->toDateString()]);
                $purchases->markSent($order, $owner);
                Carbon::setTestNow($today->copy()->subDays(23)->setTime(11, 0));
                Auth::login($store);
                $order->load('items.product');
                $purchases->receive($branch->id, $supplier, $order->items->map(fn ($i) => [
                    'product_id' => $i->product_id, 'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost, 'purchase_order_item_id' => $i->id,
                    'batch_no' => $i->product->track_batches ? 'LOT-'.strtoupper(substr(md5($branch->id.$i->id), 0, 5)) : null,
                    'expiry_date' => $i->product->track_batches ? $today->copy()->addDays(12 + ($i->id % 90))->toDateString() : null,
                ])->all(), $store, ['supplier_invoice_no' => 'INV-'.random_int(10000, 99999)], $order);

                // Pay most suppliers.
                Auth::login($owner);
                if (! str_contains($supplierName, 'Unilever')) {
                    Carbon::setTestNow($today->copy()->subDays(10)->setTime(15, 0));
                    $payments->pay($supplier, $order->total, PaymentMethod::Bank, $owner, $branch->id, [], 'TT'.random_int(100000, 999999), null, now()->toDateString());
                }
            }
        }

        // A second, partially received order and a draft order.
        $kariakoo = Branch::where('code', 'DSM01')->first();
        $azam = Supplier::where('name', 'like', 'Bakhresa%')->first();
        $azamProducts = Product::query()->sellable()->whereHas('brand', fn ($q) => $q->where('name', 'Azam'))->take(4)->get();
        Carbon::setTestNow($today->copy()->subDays(3)->setTime(10, 0));
        $order = $purchases->saveOrder($kariakoo->id, $azam, $azamProducts->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 48, 'unit_cost' => $p->cost_price])->all(), $owner, ['expected_date' => $today->copy()->addDays(2)->toDateString()]);
        $purchases->markSent($order, $owner);
        $order->load('items');
        Carbon::setTestNow($today->copy()->subDays(1)->setTime(10, 0));
        $purchases->receive($kariakoo->id, $azam, [['product_id' => $order->items[0]->product_id, 'quantity' => 24, 'unit_cost' => $order->items[0]->unit_cost, 'purchase_order_item_id' => $order->items[0]->id]], $store, ['supplier_invoice_no' => 'AZ-7781'], $order);

        Carbon::setTestNow();
        $coke = Supplier::where('name', 'like', 'Coca-Cola%')->first();
        $cokeProducts = Product::query()->sellable()->whereHas('brand', fn ($q) => $q->where('name', 'Coca-Cola'))->get();
        $purchases->saveOrder($kariakoo->id, $coke, $cokeProducts->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 72, 'unit_cost' => $p->cost_price])->all(), $owner, ['note' => 'Weekend promotion stock']);

        Auth::logout();
    }
}
