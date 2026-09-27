<?php

namespace App\Reports\Finance;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Qty;

class GrossProfitReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'gross-profit';
    }

    public function title(): string
    {
        return __('Gross profit by product / category');
    }

    public function description(): string
    {
        return __('Revenue, cost and margin — find your most profitable lines.');
    }

    public function icon(): string
    {
        return 'bi-pie-chart';
    }

    public function group(): string
    {
        return __('Finance');
    }

    public function requiresProfit(): bool
    {
        return true;
    }

    public function filters(): array
    {
        return ['by' => ['label' => __('Group by'), 'options' => ['product' => __('Product'), 'category' => __('Category')]], 'sort' => ['label' => __('Sort by'), 'options' => ['profit' => __('Profit'), 'margin' => __('Margin'), 'revenue' => __('Revenue')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $by = $f->param('by', 'product');
        $query = $this->items($f);
        if ($by === 'category') {
            $query->join('products', 'products.id', '=', 'sale_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->groupBy('categories.name')->selectRaw('COALESCE(categories.name, ?) as name', [__('Uncategorised')]);
        } else {
            $query->groupBy('sale_items.product_id', 'sale_items.name')->selectRaw('sale_items.name as name');
        }
        $rows = $query->selectRaw('SUM(sale_items.quantity) as qty, SUM(sale_items.line_total - sale_items.cart_discount_share - sale_items.tax_amount) as revenue, SUM(sale_items.cost_price * sale_items.quantity) as cost')
            ->get()->map(function ($r) {
                $profit = Money::sub($r->revenue, $r->cost);

                return ['name' => $r->name, 'qty' => Qty::round($r->qty), 'revenue' => Money::round($r->revenue), 'cost' => Money::round($r->cost), 'profit' => $profit,
                    'margin' => Money::isPositive($r->revenue) ? (float) Money::div(Money::mul($profit, 100), $r->revenue) : null];
            });
        $sort = $f->param('sort', 'profit');
        $rows = $rows->sortByDesc(fn ($r) => (float) $r[$sort])->values()->all();
        $revenue = Money::sum($rows, 'revenue');
        $profit = Money::sum($rows, 'profit');
        $top = array_slice($rows, 0, 10);

        return new ReportResult(
            columns: ['name' => ['label' => __($by === 'category' ? 'Category' : 'Product')], 'qty' => ['label' => __('Qty'), 'type' => 'number'], 'revenue' => ['label' => __('Revenue (excl. VAT)'), 'type' => 'money'],
                'cost' => ['label' => __('Cost'), 'type' => 'money'], 'profit' => ['label' => __('Gross profit'), 'type' => 'money'], 'margin' => ['label' => __('Margin'), 'type' => 'percent']],
            rows: $rows,
            totals: ['name' => __('Total'), 'revenue' => $revenue, 'cost' => Money::sum($rows, 'cost'), 'profit' => $profit, 'margin' => Money::isPositive($revenue) ? (float) Money::div(Money::mul($profit, 100), $revenue) : null],
            kpis: [
                ['label' => __('Gross profit'), 'value' => money($profit), 'icon' => 'bi-graph-up-arrow', 'color' => 'success'],
                ['label' => __('Average margin'), 'value' => Money::isPositive($revenue) ? number_format((float) Money::div(Money::mul($profit, 100), $revenue), 1).'%' : '—', 'icon' => 'bi-percent', 'color' => 'info'],
            ],
            chart: ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'name'), 'datasets' => [['label' => __('Gross profit'), 'data' => array_map('floatval', array_column($top, 'profit')), 'color' => '#16A34A']]],
        );
    }
}
