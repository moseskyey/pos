<?php

namespace App\Reports\Sales;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

class SalesByHourReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'sales-by-hour';
    }

    public function title(): string
    {
        return __('Sales by hour (peak hours)');
    }

    public function description(): string
    {
        return __('When your shop is busiest — plan staff and restocking.');
    }

    public function icon(): string
    {
        return 'bi-clock';
    }

    public function run(ReportFilters $f): ReportResult
    {
        $expr = Sql::hour('sales.created_at');
        $data = $this->sales($f)->groupBy(DB::raw($expr))->selectRaw("$expr as h, COUNT(*) as transactions, SUM(total) as total")->get()->keyBy('h');
        $rows = [];
        for ($h = 6; $h <= 23; $h++) {
            $r = $data->get($h);
            $rows[] = ['hour' => sprintf('%02d:00–%02d:59', $h, $h), 'transactions' => (int) ($r->transactions ?? 0), 'total' => Money::round($r->total ?? 0),
                'avg_per_day' => Money::div($r->total ?? 0, $f->days())];
        }
        $peak = collect($rows)->sortByDesc(fn ($r) => (float) $r['total'])->first();

        return new ReportResult(
            columns: ['hour' => ['label' => __('Hour')], 'transactions' => ['label' => __('Transactions'), 'type' => 'integer'], 'total' => ['label' => __('Sales'), 'type' => 'money'], 'avg_per_day' => ['label' => __('Avg per day'), 'type' => 'money']],
            rows: $rows,
            totals: ['hour' => __('Total'), 'transactions' => array_sum(array_column($rows, 'transactions')), 'total' => Money::sum($rows, 'total')],
            kpis: [['label' => __('Peak hour'), 'value' => $peak['hour'] ?? '—', 'icon' => 'bi-alarm', 'color' => 'warning', 'hint' => money($peak['total'] ?? 0)]],
            chart: ['type' => 'bar', 'labels' => array_map(fn ($r) => substr($r['hour'], 0, 5), $rows), 'datasets' => [['label' => __('Sales'), 'data' => array_map('floatval', array_column($rows, 'total'))]]],
        );
    }
}
