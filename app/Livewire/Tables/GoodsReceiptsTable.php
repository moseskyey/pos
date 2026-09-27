<?php

namespace App\Livewire\Tables;

use App\Models\GoodsReceipt;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class GoodsReceiptsTable extends DataTable
{
    #[Locked]
    public ?int $supplierId = null;

    protected function title(): string
    {
        return __('Goods received');
    }

    protected function query(): Builder
    {
        return GoodsReceipt::query()->with(['supplier', 'purchaseOrder', 'user'])->withCount('items')->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId));
    }

    protected function searchable(): array
    {
        return ['number', 'supplier_invoice_no', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('supplier_id', __('Suppliers'), Supplier::orderBy('name')->pluck('name', 'id')->all()),
            Filter::dateRange('received_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('GRN'), 'number')->sortable()->html(fn ($g) => '<span class="font-monospace">'.e($g->number).'</span>')->exportAs(fn ($g) => $g->number),
            Column::make(__('Received'), 'received_at')->sortable()->date(),
            Column::make(__('Supplier'))->visible(! $this->supplierId)->format(fn ($g) => $g->supplier?->name),
            Column::make(__('Invoice no.'), 'supplier_invoice_no'),
            Column::make(__('PO'))->format(fn ($g) => $g->purchaseOrder?->number ?? __('Direct')),
            Column::make(__('Items'), 'items_count')->number(),
            Column::make(__('Total'), 'total')->sortable()->money()->total(),
            Column::make(__('By'))->format(fn ($g) => $g->user?->name),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('goods-receipts.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-box-arrow-in-down', 'title' => __('No goods received yet'), 'action' => route('goods-receipts.create'), 'actionLabel' => __('Receive goods')];
    }
}
