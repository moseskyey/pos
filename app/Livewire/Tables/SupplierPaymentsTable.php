<?php

namespace App\Livewire\Tables;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class SupplierPaymentsTable extends DataTable
{
    #[Locked]
    public ?int $supplierId = null;

    protected string $defaultSort = 'paid_at';

    protected function title(): string
    {
        return __('Supplier payments');
    }

    protected function query(): Builder
    {
        return SupplierPayment::query()->with(['supplier', 'user'])->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId));
    }

    protected function searchable(): array
    {
        return ['number', 'reference', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::dateRange('paid_at', __('Date'))];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Payment'), 'number')->sortable()->html(fn ($p) => '<span class="font-monospace">'.e($p->number).'</span>')->exportAs(fn ($p) => $p->number),
            Column::make(__('Date'), 'paid_at')->sortable()->date(),
            Column::make(__('Supplier'))->visible(! $this->supplierId)->format(fn ($p) => $p->supplier?->name),
            Column::make(__('Method'))->format(fn ($p) => $p->method->label()),
            Column::make(__('Reference'), 'reference'),
            Column::make(__('Amount'), 'amount')->sortable()->money()->total(),
            Column::make(__('By'))->format(fn ($p) => $p->user?->name),
        ];
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-cash', 'title' => __('No supplier payments')];
    }
}
