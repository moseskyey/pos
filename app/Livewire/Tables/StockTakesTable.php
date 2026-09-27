<?php

namespace App\Livewire\Tables;

use App\Models\StockTake;
use Illuminate\Database\Eloquent\Builder;

class StockTakesTable extends DataTable
{
    protected function title(): string
    {
        return __('Stock takes');
    }

    protected function query(): Builder
    {
        return StockTake::query()->with(['branch', 'creator', 'category'])->withCount([
            'items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity'),
        ]);
    }

    protected function searchable(): array
    {
        return ['number', 'note'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::select('status', __('Statuses'), ['counting' => __('Counting'), 'submitted' => __('Submitted'), 'posted' => __('Posted'), 'cancelled' => __('Cancelled')])];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Number'), 'number')->sortable()->html(fn ($t) => '<span class="font-monospace">'.e($t->number).'</span>')->exportAs(fn ($t) => $t->number),
            Column::make(__('Started'), 'created_at')->sortable()->date(true),
            Column::make(__('Branch'))->format(fn ($t) => $t->branch?->name),
            Column::make(__('Scope'))->format(fn ($t) => $t->category ? $t->category->name : __('Full count')),
            Column::make(__('Progress'))->html(function ($t) {
                $pct = $t->items_count ? round($t->counted_count / $t->items_count * 100) : 0;

                return '<div class="d-flex align-items-center gap-2" style="min-width:140px"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar" style="width:'.$pct.'%"></div></div><span class="small text-body-secondary">'.$t->counted_count.'/'.$t->items_count.'</span></div>';
            })->exportAs(fn ($t) => $t->counted_count.'/'.$t->items_count),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('stock-takes.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-clipboard-check', 'title' => __('No stock takes yet'), 'message' => __('Count your shelves to find and fix differences.')];
    }
}
