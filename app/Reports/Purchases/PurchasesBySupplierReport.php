<?php

namespace App\Reports\Purchases;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PurchasesBySupplierReport extends Report
{
    public static function key(): string
    {
        return 'purchases-by-supplier';
    }

    public function title(): string
    {
        return __('Purchases by supplier');
    }

    public function description(): string
    {
        return __('Goods received, returns and payments per supplier.');
    }

    public function icon(): string
    {
        return 'bi-cart-check';
    }

    public function group(): string
    {
        return __('Customers & suppliers');
    }

    public function authorize(User $user): bool
    {
        return parent::authorize($user) && $user->can('purchases.view');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $ids = $f->branchIds ?: [0];
        $range = [$f->from, $f->to];
        $grn = DB::table('goods_receipts')->whereIn('branch_id', $ids)->whereBetween('received_at', $range)->groupBy('supplier_id')
            ->selectRaw('supplier_id, COUNT(*) as receipts, SUM(subtotal) as subtotal, SUM(tax_total) as tax, SUM(total) as total')->get()->keyBy('supplier_id');
        $returns = DB::table('purchase_returns')->whereIn('branch_id', $ids)->whereBetween('created_at', [$f->from, $f->to])->groupBy('supplier_id')->selectRaw('supplier_id, SUM(total) as t')->pluck('t', 'supplier_id');
        $paid = DB::table('supplier_payments')->whereIn('branch_id', $ids)->whereBetween('paid_at', $range)->groupBy('supplier_id')->selectRaw('supplier_id, SUM(amount) as t')->pluck('t', 'supplier_id');
        $suppliers = DB::table('suppliers')->whereIn('id', $grn->keys()->merge($returns->keys())->merge($paid->keys())->unique())->orderBy('name')->get(['id', 'name', 'balance']);

        $rows = $suppliers->map(fn ($s) => ['supplier' => $s->name, 'receipts' => (int) ($grn[$s->id]->receipts ?? 0), 'purchased' => Money::round($grn[$s->id]->total ?? 0),
            'tax' => Money::round($grn[$s->id]->tax ?? 0), 'returned' => Money::round($returns[$s->id] ?? 0), 'paid' => Money::round($paid[$s->id] ?? 0), 'balance' => Money::round($s->balance)])
            ->sortByDesc(fn ($r) => (float) $r['purchased'])->values()->all();

        return new ReportResult(
            columns: ['supplier' => ['label' => __('Supplier')], 'receipts' => ['label' => __('GRNs'), 'type' => 'integer'], 'purchased' => ['label' => __('Purchased'), 'type' => 'money'], 'tax' => ['label' => __('Input VAT'), 'type' => 'money'],
                'returned' => ['label' => __('Returned'), 'type' => 'money'], 'paid' => ['label' => __('Paid'), 'type' => 'money'], 'balance' => ['label' => __('Balance now'), 'type' => 'money']],
            rows: $rows,
            totals: ['supplier' => __('Total'), 'purchased' => Money::sum($rows, 'purchased'), 'tax' => Money::sum($rows, 'tax'), 'returned' => Money::sum($rows, 'returned'), 'paid' => Money::sum($rows, 'paid'), 'balance' => Money::sum($rows, 'balance')],
            kpis: [['label' => __('Purchased'), 'value' => money(Money::sum($rows, 'purchased')), 'icon' => 'bi-cart-check'], ['label' => __('Paid to suppliers'), 'value' => money(Money::sum($rows, 'paid')), 'icon' => 'bi-cash', 'color' => 'success']],
            chart: ['type' => 'bar', 'horizontal' => true, 'labels' => array_column(array_slice($rows, 0, 10), 'supplier'), 'datasets' => [['label' => __('Purchased'), 'data' => array_map('floatval', array_column(array_slice($rows, 0, 10), 'purchased'))]]],
        );
    }
}
