<?php

namespace App\Livewire\Tables;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

class QuotationsTable extends DataTable
{
    protected function title(): string
    {
        return __('Quotations');
    }

    protected function query(): Builder
    {
        return Sale::query()->with(['customer', 'cashier', 'convertedSale'])->whereIn('status', [SaleStatus::Quotation->value, SaleStatus::Converted->value]);
    }

    protected function searchable(): array
    {
        return ['number', 'customer.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('state', __('Statuses'), ['open' => __('Open'), 'expired' => __('Expired'), 'converted' => __('Converted')])
                ->query(fn ($q, $v) => match ($v) {
                    'open' => $q->where('status', 'quotation')->whereDate('valid_until', '>=', today()),
                    'expired' => $q->where('status', 'quotation')->whereDate('valid_until', '<', today()),
                    default => $q->where('status', 'converted'),
                }),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Quotation'), 'number')->sortable()->html(fn ($s) => '<span class="font-monospace">'.e($s->number).'</span>')->exportAs(fn ($s) => $s->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(),
            Column::make(__('Customer'))->format(fn ($s) => $s->customer?->name ?? '—'),
            Column::make(__('Valid until'), 'valid_until')->sortable()->date(),
            Column::make(__('Total'), 'total')->sortable()->money(),
            Column::make(__('Status'))->html(function ($s) {
                if ($s->status === SaleStatus::Converted) {
                    return '<span class="badge rounded-pill text-bg-success-soft status-badge">'.e(__('Converted')).'</span> <span class="small font-monospace">'.e($s->convertedSale?->number).'</span>';
                }

                return $s->valid_until?->isPast() && ! $s->valid_until->isToday()
                    ? '<span class="badge rounded-pill text-bg-danger-soft status-badge">'.e(__('Expired')).'</span>'
                    : '<span class="badge rounded-pill text-bg-info-soft status-badge">'.e(__('Open')).'</span>';
            })->exportAs(fn ($s) => $s->status->label()),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('quotations.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-file-earmark-text', 'title' => __('No quotations yet'), 'action' => route('quotations.create'), 'actionLabel' => __('New quotation')];
    }
}
