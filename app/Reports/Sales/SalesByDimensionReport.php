<?php

namespace App\Reports\Sales;

use App\Enums\PaymentMethod;
use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SalesByDimensionReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'sales-by-cashier';
    }

    public function title(): string
    {
        return __('Sales by cashier / branch / payment method');
    }

    public function description(): string
    {
        return __('Compare cashiers, branches and how customers pay.');
    }

    public function icon(): string
    {
        return 'bi-people';
    }

    public function filters(): array
    {
        return ['by' => ['label' => __('Group by'), 'options' => ['cashier' => __('Cashier'), 'branch' => __('Branch'), 'method' => __('Payment method')]]];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $by = $f->param('by', 'cashier');

        if ($by === 'method') {
            $rows = DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->where('sales.status', 'completed')->whereIn('sales.branch_id', $f->branchIds ?: [0])->whereBetween('sales.created_at', [$f->from, $f->to])
                ->groupBy('sale_payments.method')->selectRaw('sale_payments.method as name, COUNT(DISTINCT sales.id) as transactions, SUM(sale_payments.amount) as total')
                ->orderByDesc('total')->get()
                ->map(fn ($r) => ['name' => PaymentMethod::tryFrom($r->name)?->label() ?? $r->name, 'transactions' => (int) $r->transactions, 'total' => Money::round($r->total)])->all();
        } else {
            $query = $this->sales($f);
            if ($by === 'branch') {
                $query->join('branches', 'branches.id', '=', 'sales.branch_id')->groupBy('branches.name')->selectRaw('branches.name as name');
            } else {
                $query->join('users', 'users.id', '=', 'sales.user_id')->groupBy('users.name')->selectRaw('users.name as name');
            }
            $rows = $query->selectRaw('COUNT(*) as transactions, SUM(sales.total) as total, SUM(sales.discount_total) as discounts')->orderByDesc('total')->get()
                ->map(fn ($r) => ['name' => $r->name, 'transactions' => (int) $r->transactions, 'total' => Money::round($r->total), 'discounts' => Money::round($r->discounts), 'avg' => Money::div($r->total, max(1, $r->transactions))])->all();
        }

        $columns = ['name' => ['label' => __(match ($by) {
            'branch' => 'Branch', 'method' => 'Payment method', default => 'Cashier'
        })], 'transactions' => ['label' => __('Transactions'), 'type' => 'integer'], 'total' => ['label' => __('Amount'), 'type' => 'money']];
        if ($by !== 'method') {
            $columns['discounts'] = ['label' => __('Discounts'), 'type' => 'money'];
            $columns['avg'] = ['label' => __('Avg basket'), 'type' => 'money'];
        }

        return new ReportResult(
            columns: $columns,
            rows: $rows,
            totals: ['name' => __('Total'), 'transactions' => array_sum(array_column($rows, 'transactions')), 'total' => Money::sum($rows, 'total')],
            chart: ['type' => $by === 'method' ? 'doughnut' : 'bar', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => __('Amount'), 'data' => array_map('floatval', array_column($rows, 'total'))]]],
        );
    }
}
