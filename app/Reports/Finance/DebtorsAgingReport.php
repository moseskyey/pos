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
        return __('Who owes you money and how far past the due date.');
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
        $buckets = CustomerStatementService::agingBuckets();
        $rows = app(CustomerStatementService::class)->debtors()->map(fn ($d) => [
            'customer' => $d['customer']->name, 'phone' => $d['customer']->displayPhone(), 'limit' => (string) $d['customer']->credit_limit,
            ...array_intersect_key($d, $buckets), 'total' => $d['total'],
        ])->all();
        $totals = ['customer' => __('Total')];
        foreach ([...array_keys($buckets), 'total'] as $k) {
            $totals[$k] = Money::sum($rows, $k);
        }
        $overdue = Money::sub($totals['total'], $totals['current']);

        return new ReportResult(
            columns: ['customer' => ['label' => __('Customer')], 'phone' => ['label' => __('Phone')], 'limit' => ['label' => __('Credit limit'), 'type' => 'money'],
                ...collect($buckets)->map(fn ($label) => ['label' => $label, 'type' => 'money'])->all(),
                'total' => ['label' => __('Total'), 'type' => 'money']],
            rows: $rows, totals: $totals,
            kpis: [
                ['label' => __('Total owed'), 'value' => money($totals['total']), 'icon' => 'bi-journal-text', 'color' => 'danger'],
                ['label' => __('Debtors'), 'value' => number_format(count($rows)), 'icon' => 'bi-people', 'color' => 'info'],
                ['label' => __('Overdue'), 'value' => money($overdue), 'icon' => 'bi-exclamation-triangle', 'color' => 'warning'],
            ],
            chart: ['type' => 'doughnut', 'labels' => array_values($buckets), 'datasets' => [['label' => __('Owed'), 'data' => array_map(fn ($k) => (float) $totals[$k], array_keys($buckets))]]],
        );
    }
}
