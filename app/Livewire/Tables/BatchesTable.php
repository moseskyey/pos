<?php

namespace App\Livewire\Tables;

use App\Models\ProductBatch;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class BatchesTable extends DataTable
{
    protected string $defaultSort = 'expiry_date';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Batches & expiry');
    }

    protected function query(): Builder
    {
        return ProductBatch::query()->with(['product.unit', 'branch'])->where('quantity', '>', 0);
    }

    protected function searchable(): array
    {
        return ['batch_no', 'product.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('window', __('Expiry'), ['expired' => __('Expired'), '30' => __('Within 30 days'), '60' => __('Within 60 days'), '90' => __('Within 90 days')])
                ->query(fn ($q, $v) => $v === 'expired'
                    ? $q->whereDate('expiry_date', '<', today())
                    : $q->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays((int) $v))),
        ];
    }

    protected function columns(): array
    {
        $cost = auth()->user()->can('products.view_cost');

        return [
            Column::make(__('Product'))->html(fn ($b) => '<a href="'.route('products.show', $b->product_id).'" class="fw-semibold text-decoration-none">'.e($b->product?->name).'</a>')->exportAs(fn ($b) => $b->product?->name),
            Column::make(__('Batch'), 'batch_no')->sortable()->html(fn ($b) => '<span class="font-monospace">'.e($b->batch_no).'</span>')->exportAs(fn ($b) => $b->batch_no),
            Column::make(__('Branch'))->format(fn ($b) => $b->branch?->name),
            Column::make(__('Expiry'), 'expiry_date')->sortable()->date(),
            Column::make(__('Days left'))->html(function ($b) {
                $d = $b->daysToExpiry();
                if ($d === null) {
                    return '<span class="text-body-secondary">—</span>';
                }
                $cls = $d < 0 ? 'danger' : ($d <= 30 ? 'warning' : ($d <= 90 ? 'info' : 'success'));

                return '<span class="badge rounded-pill text-bg-'.$cls.'-soft">'.($d < 0 ? e(__('Expired :d days ago', ['d' => abs($d)])) : e(trans_choice(':count day|:count days', $d))).'</span>';
            })->exportAs(fn ($b) => $b->daysToExpiry()),
            Column::make(__('Quantity'), 'quantity')->sortable()->number(),
            Column::make(__('Value'))->visible($cost)->money()->format(fn ($b) => money(Money::mul($b->quantity, $b->cost_price)))->exportAs(fn ($b) => (float) Money::mul($b->quantity, $b->cost_price)),
        ];
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-calendar2-check', 'title' => __('No batches in stock'), 'message' => __('Batch-tracked products receive batch numbers and expiry dates on GRN.')];
    }
}
