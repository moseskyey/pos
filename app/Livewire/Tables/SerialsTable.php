<?php

namespace App\Livewire\Tables;

use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Builder;

class SerialsTable extends DataTable
{
    protected function title(): string
    {
        return __('Serial numbers');
    }

    protected function query(): Builder
    {
        return ProductSerial::query()->with(['product:id,name', 'customer:id,name', 'sale:id,number']);
    }

    protected function searchable(): array
    {
        return ['serial', 'product.name', 'customer.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Status'), ['in_stock' => __('In stock'), 'sold' => __('Sold'), 'defective' => __('Defective')]),
            Filter::select('warranty', __('Warranty'), ['active' => __('Under warranty'), 'expired' => __('Warranty ended')])->query(fn ($q, $v) => $v === 'active'
                ? $q->whereDate('warranty_until', '>=', today())
                : $q->whereDate('warranty_until', '<', today())),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Serial / IMEI'), 'serial')->sortable()->html(fn (ProductSerial $s) => '<span class="font-monospace fw-semibold">'.e($s->serial).'</span>')->exportAs(fn ($s) => $s->serial),
            Column::make(__('Product'))->format(fn (ProductSerial $s) => $s->product?->name),
            Column::make(__('Status'), 'status')->badge(),
            Column::make(__('Customer'))->format(fn (ProductSerial $s) => $s->customer?->name ?? '—'),
            Column::make(__('Sale'))->html(fn (ProductSerial $s) => $s->sale ? '<a href="'.e(route('sales.show', $s->sale)).'" class="font-monospace">'.e($s->sale->number).'</a>' : '—')
                ->exportAs(fn ($s) => $s->sale?->number),
            Column::make(__('Warranty until'), 'warranty_until')->sortable()->html(fn (ProductSerial $s) => $s->warranty_until
                ? '<span class="'.($s->underWarranty() ? 'text-success' : 'text-body-secondary').'">'.e(format_date($s->warranty_until)).'</span>' : '—')
                ->exportAs(fn ($s) => $s->warranty_until ? format_date($s->warranty_until) : ''),
        ];
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-upc-scan', 'title' => __('No serial numbers yet'), 'message' => __('Record them on goods received notes, below, or by scanning at the till.')];
    }
}
