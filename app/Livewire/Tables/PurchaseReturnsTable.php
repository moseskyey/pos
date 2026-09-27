<?php

namespace App\Livewire\Tables;

use App\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReturnsTable extends DataTable
{
    protected function title(): string
    {
        return __('Purchase returns');
    }

    protected function query(): Builder
    {
        return PurchaseReturn::query()->with(['supplier', 'goodsReceipt', 'user'])->withCount('items');
    }

    protected function searchable(): array
    {
        return ['number', 'reason', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::dateRange('created_at', __('Date'))];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Return'), 'number')->sortable()->html(fn ($r) => '<span class="font-monospace">'.e($r->number).'</span>')->exportAs(fn ($r) => $r->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(),
            Column::make(__('Supplier'))->format(fn ($r) => $r->supplier?->name),
            Column::make(__('GRN'))->format(fn ($r) => $r->goodsReceipt?->number ?? '—'),
            Column::make(__('Reason'), 'reason'),
            Column::make(__('Total'), 'total')->sortable()->money()->total(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('purchase-returns.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-box-arrow-up', 'title' => __('No returns to suppliers'), 'action' => route('purchase-returns.create'), 'actionLabel' => __('New return')];
    }
}
