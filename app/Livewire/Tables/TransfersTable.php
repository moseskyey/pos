<?php

namespace App\Livewire\Tables;

use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Builder;

class TransfersTable extends DataTable
{
    protected function title(): string
    {
        return __('Stock transfers');
    }

    protected function query(): Builder
    {
        return StockTransfer::query()->with(['fromBranch', 'toBranch', 'requester'])->withCount('items');
    }

    protected function searchable(): array
    {
        return ['number', 'note'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Statuses'), collect(StockTransfer::STATUSES)->mapWithKeys(fn ($s) => [$s => __(ucfirst($s))])->all()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Number'), 'number')->sortable()->html(fn ($t) => '<span class="font-monospace">'.e($t->number).'</span>')->exportAs(fn ($t) => $t->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('From'))->format(fn ($t) => $t->fromBranch?->name),
            Column::make(__('To'))->format(fn ($t) => $t->toBranch?->name),
            Column::make(__('Items'), 'items_count')->number(),
            Column::make(__('Requested by'))->format(fn ($t) => $t->requester?->name),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('transfers.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-truck', 'title' => __('No transfers yet'), 'message' => __('Move stock between branches with a full audit trail.'),
            'action' => route('transfers.create'), 'actionLabel' => __('New transfer')];
    }
}
