<?php

namespace App\Reports\Sales;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Services\CommissionService;
use App\Support\Money;

class CommissionReport extends Report
{
    public static function key(): string
    {
        return 'commission';
    }

    public function title(): string
    {
        return __('Commission & targets');
    }

    public function description(): string
    {
        return __('Sales by salesperson, commission due and progress against targets.');
    }

    public function icon(): string
    {
        return 'bi-trophy';
    }

    public function feature(): ?string
    {
        return 'commission';
    }

    public function authorize(User $user): bool
    {
        return parent::authorize($user) && $user->canAny(['targets.manage', 'reports.profit.view']);
    }

    public function run(ReportFilters $f): ReportResult
    {
        $rows = app(CommissionService::class)->summary($f->from, $f->to, $f->branchIds)->map(fn ($r) => [
            'person' => $r['user']->name,
            'transactions' => $r['transactions'],
            'net_sales' => $r['net_sales'],
            'returns' => $r['returns'],
            'commissionable' => $r['commissionable'],
            'rate' => $r['rate'] !== null ? rtrim(rtrim($r['rate'], '0'), '.').'%' : '—',
            'commission' => $r['commission'],
            'target' => $r['target'],
            'achieved' => $r['achieved'] !== null ? $r['achieved'].'%' : '—',
        ])->all();
        $totals = ['person' => __('Total'), 'transactions' => array_sum(array_column($rows, 'transactions'))];
        foreach (['net_sales', 'returns', 'commissionable', 'commission'] as $k) {
            $totals[$k] = Money::sum($rows, $k);
        }
        $best = collect($rows)->first();

        return new ReportResult(
            columns: [
                'person' => ['label' => __('Salesperson')], 'transactions' => ['label' => __('Sales'), 'type' => 'integer'],
                'net_sales' => ['label' => __('Net sales (ex VAT)'), 'type' => 'money'], 'returns' => ['label' => __('Returns'), 'type' => 'money'],
                'commissionable' => ['label' => __('Commissionable'), 'type' => 'money'], 'rate' => ['label' => __('Rate')],
                'commission' => ['label' => __('Commission'), 'type' => 'money'], 'target' => ['label' => __('Target'), 'type' => 'money'],
                'achieved' => ['label' => __('Achieved')],
            ],
            rows: $rows,
            totals: $totals,
            kpis: [
                ['label' => __('Commission due'), 'value' => money($totals['commission']), 'icon' => 'bi-cash-coin', 'color' => 'success'],
                ['label' => __('Top seller'), 'value' => $best['person'] ?? '—', 'icon' => 'bi-trophy', 'color' => 'warning', 'hint' => $best ? money($best['commissionable']) : null],
            ],
            chart: ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($rows, 'person'), 'datasets' => [
                ['label' => __('Net sales'), 'data' => array_map('floatval', array_column($rows, 'commissionable'))],
                ['label' => __('Target'), 'data' => array_map(fn ($r) => (float) ($r['target'] ?? 0), $rows)],
            ]],
        );
    }
}
