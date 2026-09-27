<?php

namespace App\Reports\Sales;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Qty;

class SalesByProductReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'sales-by-product';
    }

    public function title(): string
    {
        return __('Sales by product / category / brand');
    }

    public function description(): string
    {
        return __('Quantities and revenue per product, category or brand.');
    }

    public function icon(): string
    {
        return 'bi-box-seam';
    }

    public function filters(): array
    {
        return ['by' => ['label' => __('Group by'), 'options' => ['product' => __('Product'), 'category' => __('Category'), 'brand' => __('Brand')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $by = $f->param('by', 'product');
        $query = $this->items($f)->join('products', 'products.id', '=', 'sale_items.product_id');
        [$nameExpr, $groupCols, $bindings] = match ($by) {
            'category' => ['COALESCE(categories.name, ?)', ['categories.name'], [__('Uncategorised')]],
            'brand' => ['COALESCE(brands.name, ?)', ['brands.name'], [__('No brand')]],
            default => ['sale_items.name', ['sale_items.product_id', 'sale_items.name'], []],
        };
        if ($by === 'category') {
            $query->leftJoin('categories', 'categories.id', '=', 'products.category_id');
        }
        if ($by === 'brand') {
            $query->leftJoin('brands', 'brands.id', '=', 'products.brand_id');
        }
        $profit = $this->canSeeProfit();

        $rows = $query->groupBy($groupCols)
            ->selectRaw("$nameExpr as name, SUM(sale_items.base_quantity) as qty, SUM(sale_items.line_total - sale_items.cart_discount_share) as revenue, SUM(sale_items.tax_amount) as tax, SUM(sale_items.cost_price * sale_items.quantity) as cost, COUNT(DISTINCT sales.id) as orders", $bindings)
            ->orderByDesc('revenue')->limit(500)->get()
            ->map(function ($r) use ($profit) {
                $net = Money::sub($r->revenue, $r->tax);
                $row = ['name' => $r->name, 'orders' => (int) $r->orders, 'qty' => Qty::round($r->qty), 'revenue' => Money::round($r->revenue)];
                if ($profit) {
                    $row['profit'] = Money::sub($net, $r->cost);
                    $row['margin'] = Money::isPositive($net) ? (float) Money::div(Money::mul($row['profit'], 100), $net) : null;
                }

                return $row;
            })->all();

        $columns = ['name' => ['label' => __(ucfirst($by))], 'orders' => ['label' => __('Orders'), 'type' => 'integer'], 'qty' => ['label' => __('Qty sold'), 'type' => 'number'], 'revenue' => ['label' => __('Revenue'), 'type' => 'money']];
        if ($profit) {
            $columns['profit'] = ['label' => __('Gross profit'), 'type' => 'money'];
            $columns['margin'] = ['label' => __('Margin'), 'type' => 'percent'];
        }
        $top = array_slice($rows, 0, 10);

        return new ReportResult(
            columns: $columns,
            rows: $rows,
            totals: array_filter(['name' => __('Total'), 'qty' => Qty::sum($rows, 'qty'), 'revenue' => Money::sum($rows, 'revenue'), 'profit' => $profit ? Money::sum($rows, 'profit') : null], fn ($v) => $v !== null),
            kpis: [
                ['label' => __('Revenue'), 'value' => money(Money::sum($rows, 'revenue')), 'icon' => 'bi-cash-stack'],
                ['label' => __('Units sold'), 'value' => qty(Qty::sum($rows, 'qty')), 'icon' => 'bi-box-seam', 'color' => 'info'],
                ['label' => __('Best seller'), 'value' => $rows[0]['name'] ?? '—', 'icon' => 'bi-trophy', 'color' => 'warning'],
            ],
            chart: ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'name'), 'datasets' => [['label' => __('Revenue'), 'data' => array_map('floatval', array_column($top, 'revenue'))]]],
        );
    }
}
