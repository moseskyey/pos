<?php

namespace App\Reports\Inventory;

use App\Enums\MovementType;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class StockMovementReport extends Report
{
    public static function key(): string
    {
        return 'stock-movement';
    }

    public function title(): string
    {
        return __('Stock movement');
    }

    public function description(): string
    {
        return __('Opening, stock in, stock out and closing quantity per product.');
    }

    public function icon(): string
    {
        return 'bi-arrow-left-right';
    }

    public function group(): string
    {
        return __('Inventory');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $ids = $f->branchIds ?: [0];
        $in = [MovementType::Purchase->value, MovementType::TransferIn->value, MovementType::AdjustmentIn->value, MovementType::Opening->value, MovementType::Return->value, MovementType::Void->value];
        $sold = [MovementType::Sale->value];

        $period = DB::table('stock_movements')->whereIn('branch_id', $ids)->whereBetween('created_at', [$f->from, $f->to])
            ->groupBy('product_id')
            ->selectRaw('product_id,
                SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as qty_in,
                SUM(CASE WHEN type IN ('.implode(',', array_fill(0, count($sold), '?')).') THEN -quantity ELSE 0 END) as sold,
                SUM(CASE WHEN quantity < 0 AND type NOT IN ('.implode(',', array_fill(0, count($sold), '?')).') THEN -quantity ELSE 0 END) as other_out,
                SUM(quantity) as net', [...$sold, ...$sold])
            ->get()->keyBy('product_id');
        $opening = DB::table('stock_movements')->whereIn('branch_id', $ids)->where('created_at', '<', $f->from)
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) as qty')->pluck('qty', 'product_id');

        $productIds = $period->keys()->merge($opening->keys())->unique();
        $products = DB::table('products')->whereIn('id', $productIds)->orderBy('name')->get(['id', 'name', 'sku']);

        $rows = $products->map(function ($p) use ($period, $opening) {
            $m = $period->get($p->id);
            $open = Qty::round($opening[$p->id] ?? 0);

            return ['product' => $p->name, 'sku' => $p->sku, 'opening' => $open, 'in' => Qty::round($m->qty_in ?? 0), 'sold' => Qty::round($m->sold ?? 0),
                'other_out' => Qty::round($m->other_out ?? 0), 'closing' => Qty::add($open, $m->net ?? 0)];
        })->filter(fn ($r) => ! (Qty::isZero($r['opening']) && Qty::isZero($r['in']) && Qty::isZero($r['sold']) && Qty::isZero($r['other_out'])))->values()->all();

        $byType = DB::table('stock_movements')->whereIn('branch_id', $ids)->whereBetween('created_at', [$f->from, $f->to])->groupBy('type')->selectRaw('type, SUM(ABS(quantity)) as qty')->pluck('qty', 'type');

        return new ReportResult(
            columns: ['product' => ['label' => __('Product')], 'sku' => ['label' => __('SKU')], 'opening' => ['label' => __('Opening'), 'type' => 'number'], 'in' => ['label' => __('In'), 'type' => 'number'],
                'sold' => ['label' => __('Sold'), 'type' => 'number'], 'other_out' => ['label' => __('Other out'), 'type' => 'number'], 'closing' => ['label' => __('Closing'), 'type' => 'number']],
            rows: $rows,
            totals: ['product' => __('Total'), 'in' => Qty::sum($rows, 'in'), 'sold' => Qty::sum($rows, 'sold'), 'other_out' => Qty::sum($rows, 'other_out')],
            chart: ['type' => 'bar', 'money' => false, 'labels' => $byType->keys()->map(fn ($t) => MovementType::tryFrom($t)?->label() ?? $t)->values()->all(), 'datasets' => [['label' => __('Units'), 'data' => $byType->values()->map(fn ($v) => (float) $v)->all()]]],
        );
    }
}
