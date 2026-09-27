<?php

namespace App\Reports\Concerns;

use App\Reports\ReportFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait QueriesSales
{
    protected function sales(ReportFilters $f, string $status = 'completed'): Builder
    {
        return DB::table('sales')->where('sales.status', $status)
            ->whereIn('sales.branch_id', $f->branchIds ?: [0])
            ->whereBetween('sales.created_at', [$f->from, $f->to]);
    }

    protected function items(ReportFilters $f): Builder
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $f->branchIds ?: [0])
            ->whereBetween('sales.created_at', [$f->from, $f->to]);
    }

    protected function canSeeProfit(): bool
    {
        return (bool) auth()->user()?->can('reports.profit.view');
    }
}
