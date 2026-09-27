<?php

namespace App\Reports\Sales;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ReturnsVoidsReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'returns-voids';
    }

    public function title(): string
    {
        return __('Returns & voids');
    }

    public function description(): string
    {
        return __('Every void and refund with cashier, approver and reason.');
    }

    public function icon(): string
    {
        return 'bi-shield-exclamation';
    }

    public function group(): string
    {
        return __('Loss prevention');
    }

    public function run(ReportFilters $f): ReportResult
    {
        $voids = DB::table('sales')->where('sales.status', 'voided')->whereIn('sales.branch_id', $f->branchIds ?: [0])->whereBetween('sales.voided_at', [$f->from, $f->to])
            ->leftJoin('users as c', 'c.id', '=', 'sales.user_id')->leftJoin('users as a', 'a.id', '=', 'sales.voided_by')
            ->get(['sales.voided_at as date', 'sales.number', 'c.name as cashier', 'a.name as approver', 'sales.void_reason as reason', 'sales.total as amount'])
            ->map(fn ($r) => ['date' => $r->date, 'type' => __('Void'), 'number' => $r->number, 'cashier' => $r->cashier, 'approver' => $r->approver, 'reason' => $r->reason, 'amount' => Money::round($r->amount)]);

        $returns = DB::table('sale_returns')->whereIn('sale_returns.branch_id', $f->branchIds ?: [0])->whereBetween('sale_returns.created_at', [$f->from, $f->to])
            ->leftJoin('users as c', 'c.id', '=', 'sale_returns.user_id')->leftJoin('users as a', 'a.id', '=', 'sale_returns.approved_by')
            ->get(['sale_returns.created_at as date', 'sale_returns.number', 'c.name as cashier', 'a.name as approver', 'sale_returns.reason', 'sale_returns.refund_total as amount'])
            ->map(fn ($r) => ['date' => $r->date, 'type' => __('Return'), 'number' => $r->number, 'cashier' => $r->cashier, 'approver' => $r->approver ?? $r->cashier, 'reason' => $r->reason, 'amount' => Money::round($r->amount)]);

        $rows = $voids->concat($returns)->sortByDesc('date')->values()->all();
        $voidTotal = Money::sum($voids, 'amount');
        $returnTotal = Money::sum($returns, 'amount');

        return new ReportResult(
            columns: ['date' => ['label' => __('Date'), 'type' => 'datetime'], 'type' => ['label' => __('Type')], 'number' => ['label' => __('Document')], 'cashier' => ['label' => __('Cashier')],
                'approver' => ['label' => __('Approved by')], 'reason' => ['label' => __('Reason')], 'amount' => ['label' => __('Amount'), 'type' => 'money']],
            rows: $rows,
            totals: ['date' => __('Total'), 'amount' => Money::add($voidTotal, $returnTotal)],
            kpis: [
                ['label' => __('Voids'), 'value' => money($voidTotal), 'icon' => 'bi-x-octagon', 'color' => 'danger', 'hint' => trans_choice(':count void|:count voids', $voids->count())],
                ['label' => __('Refunds'), 'value' => money($returnTotal), 'icon' => 'bi-arrow-counterclockwise', 'color' => 'warning', 'hint' => trans_choice(':count return|:count returns', $returns->count())],
            ],
        );
    }
}
