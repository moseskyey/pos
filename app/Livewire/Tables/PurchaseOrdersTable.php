<?php

namespace App\Livewire\Tables;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class PurchaseOrdersTable extends DataTable
{
    #[Locked]
    public ?int $supplierId = null;

    protected function title(): string
    {
        return __('Purchase orders');
    }

    protected function query(): Builder
    {
        return PurchaseOrder::query()->with(['supplier', 'branch'])->withCount('items')->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId));
    }

    protected function searchable(): array
    {
        return ['number', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Statuses'), collect(PurchaseOrder::STATUSES)->mapWithKeys(fn ($s) => [$s => __(Str::headline($s))])->all()),
            Filter::select('supplier_id', __('Suppliers'), Supplier::orderBy('name')->pluck('name', 'id')->all()),
            Filter::dateRange('order_date', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('PO'), 'number')->sortable()->html(fn ($o) => '<span class="font-monospace">'.e($o->number).'</span>')->exportAs(fn ($o) => $o->number),
            Column::make(__('Date'), 'order_date')->sortable()->date(),
            Column::make(__('Supplier'))->visible(! $this->supplierId)->format(fn ($o) => $o->supplier?->name),
            Column::make(__('Expected'), 'expected_date')->date(),
            Column::make(__('Items'), 'items_count')->number(),
            Column::make(__('Total'), 'total')->sortable()->money()->total(),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('purchase-orders.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-cart-check', 'title' => __('No purchase orders yet'), 'action' => route('purchase-orders.create'), 'actionLabel' => __('New purchase order')];
    }
}
