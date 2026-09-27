<?php

namespace App\Livewire\Tables;

use App\Models\Supplier;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Locked;

class SupplierBillsTable extends DataTable
{
    #[Locked]
    public ?int $supplierId = null;

    protected string $defaultSort = 'bill_date';

    protected function title(): string
    {
        return __('Supplier bills');
    }

    protected function query(): Builder
    {
        return SupplierBill::query()->with(['supplier', 'goodsReceipt'])->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId));
    }

    protected function searchable(): array
    {
        return ['bill_no', 'description', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Statuses'), ['unpaid' => __('Unpaid'), 'partial' => __('Partially paid'), 'paid' => __('Paid'), 'overdue' => __('Overdue')])
                ->query(fn ($q, $v) => $v === 'overdue' ? $q->where('status', '!=', 'paid')->whereDate('due_date', '<', today()) : $q->where('status', $v)),
            Filter::select('supplier_id', __('Suppliers'), Supplier::orderBy('name')->pluck('name', 'id')->all()),
            Filter::dateRange('bill_date', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Bill'), 'bill_no')->sortable()->html(fn ($b) => '<span class="font-monospace">'.e($b->bill_no ?: ($b->goodsReceipt?->number ?? '#'.$b->id)).'</span>')->exportAs(fn ($b) => $b->bill_no ?: $b->goodsReceipt?->number),
            Column::make(__('Date'), 'bill_date')->sortable()->date(),
            Column::make(__('Supplier'))->visible(! $this->supplierId)->format(fn ($b) => $b->supplier?->name),
            Column::make(__('Due'), 'due_date')->sortable()->html(fn ($b) => '<span class="'.($b->isOverdue() ? 'text-danger fw-semibold' : '').'">'.e(format_date($b->due_date)).'</span>')->exportAs(fn ($b) => format_date($b->due_date)),
            Column::make(__('Total'), 'total')->sortable()->money()->total(),
            Column::make(__('Paid'), 'paid')->money()->total(),
            Column::make(__('Balance'))->money()->format(fn ($b) => money($b->balance()))->exportAs(fn ($b) => (float) $b->balance()),
            Column::make(__('Status'), 'status')->html(fn ($b) => Blade::render('<x-status-badge :status="$s" />', ['s' => $b->isOverdue() ? 'overdue' : $b->status])),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('supplier-bills.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-journal-text', 'title' => __('No supplier bills')];
    }
}
