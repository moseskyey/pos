<?php

namespace App\Reports\Finance;

use App\Reports\Concerns\QueriesSales;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ProfitLossReport extends Report
{
    use QueriesSales;

    public static function key(): string
    {
        return 'profit-loss';
    }

    public function title(): string
    {
        return __('Profit & loss');
    }

    public function description(): string
    {
        return __('Sales (excl. VAT) − cost of goods sold − expenses = net profit.');
    }

    public function icon(): string
    {
        return 'bi-calculator';
    }

    public function group(): string
    {
        return __('Finance');
    }

    public function requiresProfit(): bool
    {
        return true;
    }

    /** @return array{revenue:string, cogs:string, returns:string, returns_cost:string, gross:string, expenses:string, net:string} */
    public function figures(ReportFilters $f): array
    {
        $sales = $this->sales($f)->selectRaw('COALESCE(SUM(total - tax_total),0) as revenue')->first();
        $cogs = $this->items($f)->selectRaw('COALESCE(SUM(sale_items.cost_price * sale_items.quantity),0) as c')->value('c');
        $returns = DB::table('sale_returns')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('created_at', [$f->from, $f->to])
            ->selectRaw('COALESCE(SUM(refund_total - tax_total),0) as r')->value('r');
        $returnsCost = DB::table('sale_return_items')->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_return_items.condition', 'restock')->whereIn('sale_returns.branch_id', $f->branchIds ?: [0])->whereBetween('sale_returns.created_at', [$f->from, $f->to])
            ->selectRaw('COALESCE(SUM(sale_return_items.cost_price * sale_return_items.quantity),0) as c')->value('c');
        $expenses = DB::table('expenses')->whereNull('deleted_at')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('expense_date', [$f->from, $f->to])->sum('amount');

        $revenue = Money::sub($sales->revenue, $returns);
        $cogs = Money::sub($cogs, $returnsCost);
        $gross = Money::sub($revenue, $cogs);

        return [
            'revenue' => Money::round($revenue), 'cogs' => Money::round($cogs), 'returns' => Money::round($returns), 'returns_cost' => Money::round($returnsCost),
            'gross' => $gross, 'expenses' => Money::round($expenses), 'net' => Money::sub($gross, $expenses),
        ];
    }

    public function run(ReportFilters $f): ReportResult
    {
        $x = $this->figures($f);
        $byCategory = DB::table('expenses')->whereNull('expenses.deleted_at')->whereIn('expenses.branch_id', $f->branchIds ?: [0])
            ->whereBetween('expense_date', [$f->from, $f->to])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.name')->selectRaw('expense_categories.name as name, SUM(amount) as total')->orderByDesc('total')->get();
        $margin = fn ($v) => Money::isPositive($x['revenue']) ? (float) Money::div(Money::mul($v, 100), $x['revenue']) : null;

        $rows = [
            ['line' => __('Sales (excl. VAT)'), 'amount' => Money::add($x['revenue'], $x['returns']), 'pct' => null],
            ['line' => '  '.__('Less: returns'), 'amount' => Money::negate($x['returns']), 'pct' => null],
            ['line' => __('Net revenue'), 'amount' => $x['revenue'], 'pct' => 100.0, 'bold' => true],
            ['line' => __('Cost of goods sold'), 'amount' => Money::negate($x['cogs']), 'pct' => $margin($x['cogs'])],
            ['line' => __('Gross profit'), 'amount' => $x['gross'], 'pct' => $margin($x['gross']), 'bold' => true],
        ];
        foreach ($byCategory as $c) {
            $rows[] = ['line' => '  '.$c->name, 'amount' => Money::negate($c->total), 'pct' => $margin($c->total)];
        }
        $rows[] = ['line' => __('Total expenses'), 'amount' => Money::negate($x['expenses']), 'pct' => $margin($x['expenses']), 'bold' => true];
        $rows[] = ['line' => __('Net profit'), 'amount' => $x['net'], 'pct' => $margin($x['net']), 'bold' => true];

        return new ReportResult(
            columns: ['line' => ['label' => __('Line')], 'amount' => ['label' => __('Amount'), 'type' => 'money'], 'pct' => ['label' => __('% of revenue'), 'type' => 'percent']],
            rows: $rows,
            kpis: [
                ['label' => __('Net revenue'), 'value' => money($x['revenue']), 'icon' => 'bi-cash-stack'],
                ['label' => __('Gross profit'), 'value' => money($x['gross']), 'icon' => 'bi-graph-up-arrow', 'color' => 'success', 'hint' => $margin($x['gross']) !== null ? __('Margin :m%', ['m' => number_format($margin($x['gross']), 1)]) : null],
                ['label' => __('Expenses'), 'value' => money($x['expenses']), 'icon' => 'bi-credit-card-2-back', 'color' => 'warning'],
                ['label' => __('Net profit'), 'value' => money($x['net']), 'icon' => 'bi-trophy', 'color' => Money::isNegative($x['net']) ? 'danger' : 'info'],
            ],
            chart: ['type' => 'bar', 'labels' => [__('Revenue'), __('COGS'), __('Gross profit'), __('Expenses'), __('Net profit')],
                'datasets' => [['label' => __('Amount'), 'data' => array_map('floatval', [$x['revenue'], $x['cogs'], $x['gross'], $x['expenses'], $x['net']])]]],
        );
    }
}
