<?php

namespace App\Reports\Sales;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ShiftReconciliationReport extends Report
{
    public static function key(): string
    {
        return 'shift-reconciliation';
    }

    public function title(): string
    {
        return __('Shift & cash reconciliation');
    }

    public function description(): string
    {
        return __('Expected vs counted cash and over/short per shift.');
    }

    public function icon(): string
    {
        return 'bi-cash-coin';
    }

    public function group(): string
    {
        return __('Loss prevention');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $rows = DB::table('shifts')->where('shifts.status', 'closed')->whereIn('shifts.branch_id', $f->branchIds ?: [0])->whereBetween('shifts.opened_at', [$f->from, $f->to])
            ->join('users', 'users.id', '=', 'shifts.user_id')->join('registers', 'registers.id', '=', 'shifts.register_id')
            ->orderByDesc('shifts.opened_at')
            ->get(['shifts.number', 'shifts.opened_at', 'shifts.closed_at', 'users.name as cashier', 'registers.name as till', 'shifts.opening_float', 'shifts.expected_cash', 'shifts.counted_cash', 'shifts.over_short', 'shifts.force_closed'])
            ->map(fn ($r) => ['number' => $r->number, 'opened_at' => $r->opened_at, 'cashier' => $r->cashier, 'till' => $r->till, 'expected' => Money::round($r->expected_cash), 'counted' => Money::round($r->counted_cash), 'over_short' => Money::round($r->over_short), 'forced' => $r->force_closed ? __('Yes') : ''])->all();
        $short = Money::sum(array_filter($rows, fn ($r) => Money::isNegative($r['over_short'])), 'over_short');
        $over = Money::sum(array_filter($rows, fn ($r) => Money::isPositive($r['over_short'])), 'over_short');

        return new ReportResult(
            columns: ['number' => ['label' => __('Shift')], 'opened_at' => ['label' => __('Opened'), 'type' => 'datetime'], 'cashier' => ['label' => __('Cashier')], 'till' => ['label' => __('Till')],
                'expected' => ['label' => __('Expected'), 'type' => 'money'], 'counted' => ['label' => __('Counted'), 'type' => 'money'], 'over_short' => ['label' => __('Over/short'), 'type' => 'money'], 'forced' => ['label' => __('Force-closed')]],
            rows: $rows,
            totals: ['number' => __('Total'), 'expected' => Money::sum($rows, 'expected'), 'counted' => Money::sum($rows, 'counted'), 'over_short' => Money::sum($rows, 'over_short')],
            kpis: [
                ['label' => __('Shifts'), 'value' => number_format(count($rows)), 'icon' => 'bi-clock-history'],
                ['label' => __('Total short'), 'value' => money($short), 'icon' => 'bi-arrow-down', 'color' => 'danger'],
                ['label' => __('Total over'), 'value' => money($over), 'icon' => 'bi-arrow-up', 'color' => 'success'],
            ],
        );
    }
}
