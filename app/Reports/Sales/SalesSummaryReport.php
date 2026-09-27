<?php

namespace App\Reports\Sales;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

class SalesSummaryReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'sales-summary';
    }

    public function title(): string
    {
        return __('Sales summary');
    }

    public function description(): string
    {
        return __('Transactions, gross, discounts, VAT and net sales by day, week or month.');
    }

    public function icon(): string
    {
        return 'bi-graph-up';
    }

    public function filters(): array
    {
        return ['group' => ['label' => __('Group by'), 'options' => ['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $group = $f->param('group', $f->days() > 62 ? 'month' : 'day');
        $expr = match ($group) {
            'week' => Sql::week('sales.created_at'), 'month' => Sql::month('sales.created_at'), default => Sql::date('sales.created_at')
        };

        $rows = $this->sales($f)->groupBy(DB::raw($expr))->orderBy('period')
            ->selectRaw("$expr as period, COUNT(*) as transactions, SUM(subtotal) as gross, SUM(discount_total) as discounts, SUM(tax_total) as tax, SUM(total) as net")
            ->get()->map(fn ($r) => [
                'period' => $group === 'day' ? format_date($r->period) : $r->period,
                'transactions' => (int) $r->transactions,
                'gross' => Money::round($r->gross),
                'discounts' => Money::round($r->discounts),
                'tax' => Money::round($r->tax),
                'net' => Money::round($r->net),
                'avg' => $r->transactions ? Money::div($r->net, $r->transactions) : '0.00',
            ])->all();

        $totals = [
            'period' => __('Total'),
            'transactions' => array_sum(array_column($rows, 'transactions')),
            'gross' => Money::sum($rows, 'gross'),
            'discounts' => Money::sum($rows, 'discounts'),
            'tax' => Money::sum($rows, 'tax'),
            'net' => Money::sum($rows, 'net'),
        ];
        $totals['avg'] = $totals['transactions'] ? Money::div($totals['net'], $totals['transactions']) : '0.00';

        return new ReportResult(
            columns: [
                'period' => ['label' => __('Period')],
                'transactions' => ['label' => __('Transactions'), 'type' => 'integer'],
                'gross' => ['label' => __('Gross'), 'type' => 'money'],
                'discounts' => ['label' => __('Discounts'), 'type' => 'money'],
                'tax' => ['label' => __('VAT'), 'type' => 'money'],
                'net' => ['label' => __('Net sales'), 'type' => 'money'],
                'avg' => ['label' => __('Avg basket'), 'type' => 'money'],
            ],
            rows: $rows,
            totals: $totals,
            kpis: [
                ['label' => __('Net sales'), 'value' => money($totals['net']), 'icon' => 'bi-cash-stack'],
                ['label' => __('Transactions'), 'value' => number_format($totals['transactions']), 'icon' => 'bi-receipt', 'color' => 'info'],
                ['label' => __('Average basket'), 'value' => money($totals['avg']), 'icon' => 'bi-basket', 'color' => 'success'],
                ['label' => __('Discounts'), 'value' => money($totals['discounts']), 'icon' => 'bi-percent', 'color' => 'warning'],
            ],
            chart: ['type' => 'line', 'labels' => array_column($rows, 'period'), 'datasets' => [['label' => __('Net sales'), 'data' => array_map('floatval', array_column($rows, 'net'))]]],
        );
    }
}
