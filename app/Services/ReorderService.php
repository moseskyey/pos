<?php

namespace App\Services;

use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Support\Qty;
use Illuminate\Support\Collection;

/**
 * Products at or below their reorder level, grouped by last supplier.
 */
class ReorderService
{
    public function __construct(protected StockService $stock) {}

    /** @return Collection<int|string, array{supplier_id: ?int, supplier: ?string, items: Collection}> */
    public function suggestions(int $branchId): Collection
    {
        $products = Product::query()->active()->sellable()->where('track_stock', true)->where('reorder_level', '>', 0)->with('unit')->get();
        $stock = $this->stock->availableMany($branchId, $products->pluck('id')->all());

        $low = $products->filter(fn ($p) => Qty::lte($stock[$p->id] ?? 0, $p->reorder_level));
        if ($low->isEmpty()) {
            return collect();
        }

        $lastSupplier = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->whereIn('goods_receipt_items.product_id', $low->pluck('id'))
            ->orderBy('goods_receipts.received_at')->orderBy('goods_receipt_items.id')
            ->get(['goods_receipt_items.product_id', 'goods_receipts.supplier_id', 'suppliers.name as supplier_name', 'goods_receipt_items.unit_cost'])
            ->keyBy('product_id');

        // A preferred supplier (set on the product page) wins over whoever supplied it last.
        $preferred = ProductSupplier::query()->with('supplier:id,name')->where('is_preferred', true)
            ->whereIn('product_id', $low->pluck('id'))->get()->keyBy('product_id');

        return $low->map(function (Product $p) use ($stock, $lastSupplier, $preferred) {
            $onHand = $stock[$p->id] ?? '0';
            $target = Qty::mul($p->reorder_level, 3);
            $last = $lastSupplier->get($p->id);
            $pref = $preferred->get($p->id);

            return [
                'product' => $p,
                'on_hand' => $onHand,
                'suggested' => (float) Qty::max(Qty::sub($target, $onHand), $p->reorder_level),
                'unit_cost' => (string) ($pref?->last_cost ?? $last?->unit_cost ?? $p->cost_price),
                'supplier_id' => $pref?->supplier_id ?? $last?->supplier_id,
                'supplier' => $pref?->supplier?->name ?? $last?->supplier_name,
            ];
        })->groupBy(fn ($row) => $row['supplier_id'] ?? 0)
            ->map(fn ($rows, $supplierId) => ['supplier_id' => $supplierId ?: null, 'supplier' => $rows->first()['supplier'], 'items' => $rows->values()]);
    }
}
