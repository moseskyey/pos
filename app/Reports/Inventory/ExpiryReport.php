<?php

namespace App\Reports\Inventory;

use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpiryReport extends Report
{
    public static function key(): string
    {
        return 'expiry';
    }

    public function title(): string
    {
        return __('Expiry report');
    }

    public function description(): string
    {
        return __('Batches expired or expiring within 30, 60 or 90 days.');
    }

    public function icon(): string
    {
        return 'bi-calendar2-x';
    }

    public function group(): string
    {
        return __('Inventory');
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return ['window' => ['label' => __('Window'), 'options' => ['expired' => __('Expired'), '30' => __('Next 30 days'), '60' => __('Next 60 days'), '90' => __('Next 90 days')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $window = $f->param('window', '30');
        $q = DB::table('product_batches')->join('products', 'products.id', '=', 'product_batches.product_id')->join('branches', 'branches.id', '=', 'product_batches.branch_id')
            ->whereIn('product_batches.branch_id', $f->branchIds ?: [0])->where('product_batches.quantity', '>', 0)->whereNotNull('expiry_date');
        $window === 'expired' ? $q->whereDate('expiry_date', '<', today()) : $q->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays((int) $window));

        $rows = $q->orderBy('expiry_date')->get(['products.name', 'product_batches.batch_no', 'branches.name as branch', 'product_batches.expiry_date', 'product_batches.quantity', 'product_batches.cost_price', 'products.retail_price'])
            ->map(fn ($r) => ['product' => $r->name, 'batch' => $r->batch_no, 'branch' => $r->branch, 'expiry' => $r->expiry_date,
                'days' => (int) today()->diffInDays(Carbon::parse($r->expiry_date), false), 'qty' => $r->quantity, 'value' => Money::mul($r->quantity, $r->cost_price)])->all();

        return new ReportResult(
            columns: ['product' => ['label' => __('Product')], 'batch' => ['label' => __('Batch')], 'branch' => ['label' => __('Branch')], 'expiry' => ['label' => __('Expiry'), 'type' => 'date'],
                'days' => ['label' => __('Days left'), 'type' => 'integer'], 'qty' => ['label' => __('Qty'), 'type' => 'number'], 'value' => ['label' => __('Value at cost'), 'type' => 'money']],
            rows: $rows, totals: ['product' => __('Total'), 'value' => Money::sum($rows, 'value')],
            kpis: [['label' => __('Batches'), 'value' => number_format(count($rows)), 'icon' => 'bi-calendar2-x', 'color' => 'danger'], ['label' => __('Value at risk'), 'value' => money(Money::sum($rows, 'value')), 'icon' => 'bi-cash', 'color' => 'warning']],
        );
    }
}
