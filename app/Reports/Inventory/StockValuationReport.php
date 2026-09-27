<?php

namespace App\Reports\Inventory;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

class StockValuationReport extends Report
{
    public static function key(): string
    {
        return 'stock-valuation';
    }

    public function title(): string
    {
        return __('Stock valuation');
    }

    public function description(): string
    {
        return __('Stock on hand valued at cost and at retail price.');
    }

    public function icon(): string
    {
        return 'bi-safe';
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
        return ['by' => ['label' => __('Group by'), 'options' => ['product' => __('Product'), 'category' => __('Category'), 'branch' => __('Branch')]]];
    }

    public function authorize(User $user): bool
    {
        return parent::authorize($user) && $user->can('stock.value.view');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $by = $f->param('by', 'category');
        $cost = auth()->user()?->can('products.view_cost') ?? false;
        $q = DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('products.deleted_at')->where('products.track_stock', true)->where('product_stocks.quantity', '>', 0)
            ->whereIn('product_stocks.branch_id', $f->branchIds ?: [0]);
        match ($by) {
            'branch' => $q->join('branches', 'branches.id', '=', 'product_stocks.branch_id')->groupBy('branches.name')->selectRaw('branches.name as name'),
            'product' => $q->groupBy('products.id', 'products.name')->selectRaw('products.name as name'),
            default => $q->leftJoin('categories', 'categories.id', '=', 'products.category_id')->groupBy('categories.name')->selectRaw('COALESCE(categories.name, ?) as name', [__('Uncategorised')]),
        };
        $rows = $q->selectRaw('SUM(product_stocks.quantity) as qty, COUNT(DISTINCT products.id) as skus, SUM(product_stocks.quantity * products.cost_price) as cost_value, SUM(product_stocks.quantity * products.retail_price) as retail_value')
            ->orderByDesc('retail_value')->get()
            ->map(fn ($r) => array_filter(['name' => $r->name, 'skus' => (int) $r->skus, 'qty' => Qty::round($r->qty), 'cost_value' => $cost ? Money::round($r->cost_value) : null,
                'retail_value' => Money::round($r->retail_value), 'potential' => $cost ? Money::sub($r->retail_value, $r->cost_value) : null], fn ($v) => $v !== null))->all();

        $columns = ['name' => ['label' => __(ucfirst($by))], 'skus' => ['label' => __('Products'), 'type' => 'integer'], 'qty' => ['label' => __('Units'), 'type' => 'number']];
        if ($cost) {
            $columns['cost_value'] = ['label' => __('Value at cost'), 'type' => 'money'];
        }
        $columns['retail_value'] = ['label' => __('Value at retail'), 'type' => 'money'];
        if ($cost) {
            $columns['potential'] = ['label' => __('Potential profit'), 'type' => 'money'];
        }
        $totals = array_filter(['name' => __('Total'), 'qty' => Qty::sum($rows, 'qty'), 'cost_value' => $cost ? Money::sum($rows, 'cost_value') : null, 'retail_value' => Money::sum($rows, 'retail_value'), 'potential' => $cost ? Money::sum($rows, 'potential') : null], fn ($v) => $v !== null);

        return new ReportResult(
            columns: $columns, rows: $rows, totals: $totals,
            kpis: array_values(array_filter([
                $cost ? ['label' => __('Value at cost'), 'value' => money($totals['cost_value']), 'icon' => 'bi-cash-stack'] : null,
                ['label' => __('Value at retail'), 'value' => money($totals['retail_value']), 'icon' => 'bi-tags', 'color' => 'success'],
                ['label' => __('Units on hand'), 'value' => qty($totals['qty']), 'icon' => 'bi-stack', 'color' => 'info'],
            ])),
            chart: $by !== 'product' ? ['type' => 'doughnut', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => __('Retail value'), 'data' => array_map('floatval', array_column($rows, 'retail_value'))]]] : null,
        );
    }
}
