<?php

namespace App\Livewire\Tables;

use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Builder;

class ReturnsTable extends DataTable
{
    protected function title(): string
    {
        return __('Returns');
    }

    protected function query(): Builder
    {
        return SaleReturn::query()->with(['sale', 'customer', 'user', 'branch'])->withCount('items');
    }

    protected function searchable(): array
    {
        return ['number', 'reason', 'sale.number', 'customer.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('refund_method', __('Refund methods'), ['cash' => __('Cash'), 'mpesa' => 'M-Pesa', 'store_credit' => __('Store credit'), 'account' => __('Customer account'), 'bank' => __('Bank')]),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Return'), 'number')->sortable()->html(fn ($r) => '<span class="font-monospace">'.e($r->number).'</span>')->exportAs(fn ($r) => $r->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Sale'))->html(fn ($r) => '<a href="'.route('sales.show', $r->sale_id).'" class="font-monospace small text-decoration-none">'.e($r->sale?->number).'</a>')->exportAs(fn ($r) => $r->sale?->number),
            Column::make(__('Customer'))->format(fn ($r) => $r->customer?->name ?? __('Walk-in')),
            Column::make(__('Reason'), 'reason'),
            Column::make(__('Refund'))->format(fn ($r) => $r->refundLabel()),
            Column::make(__('Amount'), 'refund_total')->sortable()->money()->total(),
            Column::make(__('By'))->format(fn ($r) => $r->user?->name),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('returns.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-arrow-counterclockwise', 'title' => __('No returns yet'), 'action' => route('returns.create'), 'actionLabel' => __('New return')];
    }
}
