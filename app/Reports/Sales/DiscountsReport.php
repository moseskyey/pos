<?php

namespace App\Reports\Sales;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class DiscountsReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'discounts';
    }

    public function title(): string
    {
        return __('Discounts given (by cashier)');
    }

    public function description(): string
    {
        return __('Who gives discounts, how much, and manager overrides.');
    }

    public function icon(): string
    {
        return 'bi-percent';
    }

    public function group(): string
    {
        return __('Loss prevention');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $rows = $this->sales($f)->join('users', 'users.id', '=', 'sales.user_id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as user_id, users.name as name, COUNT(*) as sales, SUM(CASE WHEN discount_total > 0 THEN 1 ELSE 0 END) as discounted, SUM(discount_total) as discounts, SUM(subtotal) as gross')
            ->orderByDesc('discounts')->get();
        $approvals = DB::table('approvals')->where('action', 'discount')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('created_at', [$f->from, $f->to])
            ->groupBy('requested_by')->selectRaw('requested_by, COUNT(*) as c')->pluck('c', 'requested_by');

        $data = $rows->map(fn ($r) => [
            'name' => $r->name,
            'sales' => (int) $r->sales,
            'discounted' => (int) $r->discounted,
            'discounts' => Money::round($r->discounts),
            'rate' => Money::isPositive($r->gross) ? (float) Money::div(Money::mul($r->discounts, 100), $r->gross) : 0,
            'overrides' => (int) ($approvals[$r->user_id] ?? 0),
        ])->all();

        return new ReportResult(
            columns: ['name' => ['label' => __('Cashier')], 'sales' => ['label' => __('Sales'), 'type' => 'integer'], 'discounted' => ['label' => __('Discounted sales'), 'type' => 'integer'],
                'discounts' => ['label' => __('Discount amount'), 'type' => 'money'], 'rate' => ['label' => __('% of gross'), 'type' => 'percent'], 'overrides' => ['label' => __('Manager overrides'), 'type' => 'integer']],
            rows: $data,
            totals: ['name' => __('Total'), 'discounts' => Money::sum($data, 'discounts'), 'overrides' => array_sum(array_column($data, 'overrides'))],
            kpis: [['label' => __('Total discounts'), 'value' => money(Money::sum($data, 'discounts')), 'icon' => 'bi-percent', 'color' => 'warning']],
            chart: ['type' => 'bar', 'labels' => array_column($data, 'name'), 'datasets' => [['label' => __('Discounts'), 'data' => array_map('floatval', array_column($data, 'discounts')), 'color' => '#F59E0B']]],
        );
    }
}
