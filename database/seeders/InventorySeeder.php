<?php

namespace Database\Seeders;

use App\Enums\AdjustmentReason;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\StockAdjustmentService;
use App\Services\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Opening stock for both branches, batches with expiry dates, a pending
 * adjustment and a completed transfer.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@dukapos.test')->firstOrFail();
        Auth::login($owner);
        Carbon::setTestNow(now()->subDays(45)->setTime(8, 0));
        $adjustments = app(StockAdjustmentService::class);
        $products = Product::query()->sellable()->where('track_stock', true)->get();

        foreach (Branch::orderBy('id')->get() as $index => $branch) {
            $items = [];
            foreach ($products as $i => $product) {
                // Leave a few products low / out of stock for demo alerts.
                $qty = match (true) {
                    $i % 17 === 3 => 0,
                    $i % 11 === 5 => max(1, (int) $product->reorder_level - 2),
                    default => (int) max(5, $product->reorder_level * (3 + ($i + $index) % 4)),
                };
                if ($qty <= 0) {
                    continue;
                }
                if ($product->track_batches) {
                    $half = (int) ceil($qty / 2);
                    $items[] = ['product_id' => $product->id, 'direction' => 'in', 'quantity' => $half, 'unit_cost' => $product->cost_price,
                        'batch_no' => 'B'.now()->subMonths(2)->format('ym').'-'.$product->id, 'expiry_date' => now()->addDays(5 + ($i * 7) % 40)->toDateString()];
                    $items[] = ['product_id' => $product->id, 'direction' => 'in', 'quantity' => $qty - $half, 'unit_cost' => $product->cost_price,
                        'batch_no' => 'B'.now()->format('ym').'-'.$product->id, 'expiry_date' => now()->addMonths(8)->toDateString()];
                } else {
                    $items[] = ['product_id' => $product->id, 'direction' => 'in', 'quantity' => $qty, 'unit_cost' => $product->cost_price];
                }
            }
            $adjustments->create($branch->id, AdjustmentReason::Opening, $items, $owner, __('Opening stock'));
        }

        $kariakoo = Branch::where('code', 'DSM01')->first();
        $mbezi = Branch::where('code', 'DSM02')->first();
        $store = User::where('email', 'store@dukapos.test')->first();

        // A pending damaged-stock adjustment awaiting manager approval.
        Carbon::setTestNow(now()->addDays(44));
        Auth::login($store);
        $adjustments->create($kariakoo->id, AdjustmentReason::Damaged, [
            ['product_id' => $products->firstWhere('name', 'Coca-Cola 350ml Bottle')->id, 'direction' => 'out', 'quantity' => 3],
        ], $store, 'Crate dropped during offloading');

        // A completed transfer Kariakoo → Mbezi.
        Carbon::setTestNow(now()->subDays(20));
        Auth::login($owner);
        $transfers = app(StockTransferService::class);
        $transfer = $transfers->request($kariakoo->id, $mbezi->id, [
            ['product_id' => $products->firstWhere('name', 'Azam Maji 500ml')->id, 'quantity' => 24],
            ['product_id' => $products->firstWhere('name', 'Sukari 1kg')->id, 'quantity' => 10],
        ], $owner, 'Weekly restock');
        $transfers->dispatch($transfer, $owner);
        $transfers->receive($transfer, $owner);

        // A transfer awaiting approval.
        Carbon::setTestNow();
        Auth::login($store);
        $transfers->request($mbezi->id, $kariakoo->id, [
            ['product_id' => $products->firstWhere('name', 'Mafuta ya Kupikia Korie 1L')->id, 'quantity' => 6],
        ], $store, 'Kariakoo running low');

        Auth::logout();
        Carbon::setTestNow();
    }
}
