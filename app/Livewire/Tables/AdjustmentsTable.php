<?php

namespace App\Livewire\Tables;

use App\Enums\AdjustmentReason;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Builder;

class AdjustmentsTable extends DataTable
{
    protected function title(): string
    {
        return __('Stock adjustments');
    }

    protected function query(): Builder
    {
        return StockAdjustment::query()->with(['creator', 'approver', 'branch'])->withCount('items');
    }

    protected function searchable(): array
    {
        return ['number', 'note'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Statuses'), ['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')]),
            Filter::select('reason', __('Reasons'), AdjustmentReason::options()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Number'), 'number')->sortable()->html(fn ($a) => '<span class="font-monospace">'.e($a->number).'</span>')->exportAs(fn ($a) => $a->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Branch'))->format(fn ($a) => $a->branch?->name),
            Column::make(__('Reason'), 'reason')->format(fn ($a) => $a->reason->label()),
            Column::make(__('Items'), 'items_count')->number(),
            Column::make(__('By'))->format(fn ($a) => $a->creator?->name),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('adjustments.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-sliders', 'title' => __('No adjustments yet'), 'message' => __('Record damaged, expired or found stock, or post opening stock.'),
            'action' => route('adjustments.create'), 'actionLabel' => __('New adjustment')];
    }
}
