<?php

namespace App\Reports\Inventory;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class LowStockReport extends Report
{
    public static function key(): string
    {
        return 'low-stock';
    }

    public function title(): string
    {
        return __('Low, out-of-stock & dead stock');
    }

    public function description(): string
    {
        return __('What to reorder, and slow movers with no sales in N days.');
    }

    public function icon(): string
    {
        return 'bi-exclamation-triangle';
    }

    public function group(): string
    {
        return __('Inventory');
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [
            'type' => ['label' => __('Show'), 'options' => ['low' => __('Low stock'), 'out' => __('Out of stock'), 'dead' => __('Dead stock (no sales)')]],
            'days' => ['label' => __('Dead stock days'), 'type' => 'number', 'default' => 60],
        ];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $type = $f->param('type', 'low');
        $ids = $f->branchIds ?: [0];
        $stock = DB::table('products')->where('products.is_active', true)->where('products.has_variants', false)->where('products.track_stock', true)->whereNull('products.deleted_at')
            ->leftJoin('product_stocks', fn ($j) => $j->on('product_stocks.product_id', '=', 'products.id')->whereIn('product_stocks.branch_id', $ids))
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level', 'products.cost_price', 'categories.name')
            ->selectRaw('products.id, products.name, products.sku, products.reorder_level, products.cost_price, categories.name as category, COALESCE(SUM(product_stocks.quantity), 0) as qty');

        if ($type === 'dead') {
            $days = max(1, (int) $f->param('days', 60));
            $sold = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')
                ->whereIn('sales.branch_id', $ids)->where('sales.created_at', '>=', now()->subDays($days))->distinct()->pluck('sale_items.product_id');
            $last = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')->whereIn('sales.branch_id', $ids)
                ->groupBy('sale_items.product_id')->selectRaw('sale_items.product_id, MAX(sales.created_at) as last')->pluck('last', 'product_id');
            $rows = $stock->havingRaw('COALESCE(SUM(product_stocks.quantity), 0) > 0')->get()->whereNotIn('id', $sold->all())
                ->map(fn ($r) => ['product' => $r->name, 'sku' => $r->sku, 'category' => $r->category, 'qty' => Qty::round($r->qty), 'value' => Money::mul($r->qty, $r->cost_price), 'last_sale' => $last[$r->id] ?? null])
                ->sortByDesc(fn ($r) => (float) $r['value'])->values()->all();

            return new ReportResult(
                columns: ['product' => ['label' => __('Product')], 'sku' => ['label' => __('SKU')], 'category' => ['label' => __('Category')], 'qty' => ['label' => __('On hand'), 'type' => 'number'],
                    'value' => ['label' => __('Value at cost'), 'type' => 'money'], 'last_sale' => ['label' => __('Last sold'), 'type' => 'date']],
                rows: $rows, totals: ['product' => __('Total'), 'value' => Money::sum($rows, 'value')],
                kpis: [['label' => __('Dead stock value'), 'value' => money(Money::sum($rows, 'value')), 'icon' => 'bi-hourglass-bottom', 'color' => 'danger', 'hint' => __('No sales in :d days', ['d' => $days])]],
            );
        }

        $rows = ($type === 'out'
            ? $stock->havingRaw('COALESCE(SUM(product_stocks.quantity), 0) <= 0')
            : $stock->havingRaw('COALESCE(SUM(product_stocks.quantity), 0) > 0 AND COALESCE(SUM(product_stocks.quantity), 0) <= products.reorder_level'))
            ->orderBy('qty')->get()
            ->map(fn ($r) => ['product' => $r->name, 'sku' => $r->sku, 'category' => $r->category, 'qty' => Qty::round($r->qty), 'reorder' => Qty::round($r->reorder_level), 'suggested' => Qty::max(Qty::sub(Qty::mul($r->reorder_level, 3), $r->qty), 0)])->all();

        return new ReportResult(
            columns: ['product' => ['label' => __('Product')], 'sku' => ['label' => __('SKU')], 'category' => ['label' => __('Category')], 'qty' => ['label' => __('On hand'), 'type' => 'number'],
                'reorder' => ['label' => __('Reorder level'), 'type' => 'number'], 'suggested' => ['label' => __('Suggested order'), 'type' => 'number']],
            rows: $rows,
            kpis: [['label' => $type === 'out' ? __('Out of stock') : __('Low stock'), 'value' => number_format(count($rows)), 'icon' => 'bi-exclamation-triangle', 'color' => $type === 'out' ? 'danger' : 'warning']],
        );
    }
}
