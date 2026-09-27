<?php

namespace App\Reports\Purchases;

use App\Models\Supplier;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Services\SupplierLedgerService;
use App\Support\Money;

class SupplierAgingReport extends Report
{
    public static function key(): string
    {
        return 'supplier-aging';
    }

    public function title(): string
    {
        return __('Supplier aging');
    }

    public function description(): string
    {
        return __('What you owe each supplier by age of bill.');
    }

    public function icon(): string
    {
        return 'bi-building';
    }

    public function group(): string
    {
        return __('Customers & suppliers');
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function authorize(User $user): bool
    {
        return parent::authorize($user) && $user->can('supplier.payments');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $ledger = app(SupplierLedgerService::class);
        $rows = Supplier::query()->where('balance', '>', 0)->orderByDesc('balance')->get()->map(fn (Supplier $s) => ['supplier' => $s->name, 'terms' => $s->payment_terms_days] + $ledger->aging($s))->all();
        $totals = ['supplier' => __('Total')];
        foreach (['current', '31_60', '61_90', 'over_90', 'total'] as $k) {
            $totals[$k] = Money::sum($rows, $k);
        }

        return new ReportResult(
            columns: ['supplier' => ['label' => __('Supplier')], 'terms' => ['label' => __('Terms (days)'), 'type' => 'integer'], 'current' => ['label' => __('0–30 days'), 'type' => 'money'], '31_60' => ['label' => __('31–60'), 'type' => 'money'],
                '61_90' => ['label' => __('61–90'), 'type' => 'money'], 'over_90' => ['label' => __('90+'), 'type' => 'money'], 'total' => ['label' => __('Total'), 'type' => 'money']],
            rows: $rows, totals: $totals,
            kpis: [['label' => __('Total payable'), 'value' => money($totals['total']), 'icon' => 'bi-journal-text', 'color' => 'danger']],
        );
    }
}
