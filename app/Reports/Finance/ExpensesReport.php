<?php

namespace App\Reports\Finance;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ExpensesReport extends Report
{
    public static function key(): string
    {
        return 'expenses';
    }

    public function title(): string
    {
        return __('Expenses report');
    }

    public function description(): string
    {
        return __('Spending by category with every expense line.');
    }

    public function icon(): string
    {
        return 'bi-credit-card-2-back';
    }

    public function group(): string
    {
        return __('Finance');
    }

    public function filters(): array
    {
        return ['view' => ['label' => __('Show'), 'options' => ['category' => __('By category'), 'detail' => __('Every expense')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $base = DB::table('expenses')->whereNull('expenses.deleted_at')->whereIn('expenses.branch_id', $f->branchIds ?: [0])
            ->whereBetween('expense_date', [$f->from, $f->to])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        $byCategory = (clone $base)->groupBy('expense_categories.name')->selectRaw('expense_categories.name as category, COUNT(*) as count, SUM(amount) as total')->orderByDesc('total')->get();
        $total = Money::sum($byCategory, 'total');
        $chart = ['type' => 'doughnut', 'labels' => $byCategory->pluck('category')->all(), 'datasets' => [['label' => __('Expenses'), 'data' => $byCategory->pluck('total')->map(fn ($v) => (float) $v)->all()]]];

        if ($f->param('view', 'category') === 'detail') {
            $rows = (clone $base)->orderByDesc('expense_date')->get(['expenses.expense_date as date', 'expenses.number', 'expense_categories.name as category', 'expenses.payee', 'expenses.description', 'expenses.payment_method', 'expenses.amount'])
                ->map(fn ($r) => ['date' => $r->date, 'number' => $r->number, 'category' => $r->category, 'description' => trim(($r->payee ? $r->payee.' · ' : '').$r->description), 'method' => $r->payment_method, 'amount' => Money::round($r->amount)])->all();

            return new ReportResult(
                columns: ['date' => ['label' => __('Date'), 'type' => 'date'], 'number' => ['label' => __('Number')], 'category' => ['label' => __('Category')], 'description' => ['label' => __('Description')], 'method' => ['label' => __('Method')], 'amount' => ['label' => __('Amount'), 'type' => 'money']],
                rows: $rows, totals: ['date' => __('Total'), 'amount' => $total], kpis: [['label' => __('Total expenses'), 'value' => money($total), 'icon' => 'bi-credit-card-2-back', 'color' => 'warning']], chart: $chart,
            );
        }

        $rows = $byCategory->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->count, 'total' => Money::round($r->total), 'share' => Money::isPositive($total) ? (float) Money::div(Money::mul($r->total, 100), $total) : 0])->all();

        return new ReportResult(
            columns: ['category' => ['label' => __('Category')], 'count' => ['label' => __('Entries'), 'type' => 'integer'], 'total' => ['label' => __('Amount'), 'type' => 'money'], 'share' => ['label' => __('Share'), 'type' => 'percent']],
            rows: $rows, totals: ['category' => __('Total'), 'total' => $total],
            kpis: [['label' => __('Total expenses'), 'value' => money($total), 'icon' => 'bi-credit-card-2-back', 'color' => 'warning'], ['label' => __('Per day'), 'value' => money(Money::div($total, $f->days())), 'icon' => 'bi-calendar-day', 'color' => 'info']],
            chart: $chart,
        );
    }
}
