<?php

namespace App\Reports\Finance;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Services\CustomerStatementService;
use App\Support\Money;

class DebtorsAgingReport extends Report
{
    public static function key(): string
    {
        return 'debtors-aging';
    }

    public function title(): string
    {
        return __('Debtors aging (deni)');
    }

    public function description(): string
    {
        return __('Who owes you money and for how long.');
    }

    public function icon(): string
    {
        return 'bi-people';
    }

    public function group(): string
    {
        return __('Customers & suppliers');
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function run(ReportFilters $f): ReportResult
    {
        $rows = app(CustomerStatementService::class)->debtors()->map(fn ($d) => [
            'customer' => $d['customer']->name, 'phone' => $d['customer']->displayPhone(), 'limit' => (string) $d['customer']->credit_limit,
            'current' => $d['current'], '31_60' => $d['31_60'], '61_90' => $d['61_90'], 'over_90' => $d['over_90'], 'total' => $d['total'],
        ])->all();
        $totals = ['customer' => __('Total')];
        foreach (['current', '31_60', '61_90', 'over_90', 'total'] as $k) {
            $totals[$k] = Money::sum($rows, $k);
        }

        return new ReportResult(
            columns: ['customer' => ['label' => __('Customer')], 'phone' => ['label' => __('Phone')], 'limit' => ['label' => __('Credit limit'), 'type' => 'money'],
                'current' => ['label' => __('0–30 days'), 'type' => 'money'], '31_60' => ['label' => __('31–60'), 'type' => 'money'], '61_90' => ['label' => __('61–90'), 'type' => 'money'],
                'over_90' => ['label' => __('90+'), 'type' => 'money'], 'total' => ['label' => __('Total'), 'type' => 'money']],
            rows: $rows, totals: $totals,
            kpis: [
                ['label' => __('Total owed'), 'value' => money($totals['total']), 'icon' => 'bi-journal-text', 'color' => 'danger'],
                ['label' => __('Debtors'), 'value' => number_format(count($rows)), 'icon' => 'bi-people', 'color' => 'info'],
                ['label' => __('Over 90 days'), 'value' => money($totals['over_90']), 'icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
            ],
            chart: ['type' => 'doughnut', 'labels' => [__('0–30 days'), __('31–60'), __('61–90'), __('90+')], 'datasets' => [['label' => __('Owed'), 'data' => array_map('floatval', [$totals['current'], $totals['31_60'], $totals['61_90'], $totals['over_90']])]]],
        );
    }
}
