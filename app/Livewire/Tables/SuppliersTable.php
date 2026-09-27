<?php

namespace App\Livewire\Tables;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

class SuppliersTable extends DataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Suppliers');
    }

    protected function query(): Builder
    {
        return Supplier::query();
    }

    protected function searchable(): array
    {
        return ['name', 'contact_person', 'phone', 'email', 'tin'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('owing', __('Balance'), ['owing' => __('We owe them')])->query(fn ($q) => $q->where('balance', '>', 0)),
            Filter::boolean('is_active', __('Active')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Supplier'), 'name')->sortable()->html(fn ($s) => '<div class="fw-semibold">'.e($s->name).'</div><div class="small text-body-secondary">'.e($s->contact_person).'</div>')->exportAs(fn ($s) => $s->name),
            Column::make(__('Phone'), 'phone')->format(fn ($s) => $s->displayPhone() ?: '—'),
            Column::make(__('TIN'), 'tin'),
            Column::make(__('Terms'), 'payment_terms_days')->format(fn ($s) => trans_choice(':count day|:count days', $s->payment_terms_days)),
            Column::make(__('Balance owed'), 'balance')->sortable()->money()->total()->html(fn ($s) => $s->balance > 0 ? '<span class="fw-semibold text-danger">'.e(money($s->balance)).'</span>' : '<span class="text-body-secondary">—</span>'),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('suppliers.show', $row);
    }

    protected function rowActions(mixed $row): ?string
    {
        return auth()->user()->can('suppliers.manage') ? '<a href="'.route('suppliers.edit', $row).'" class="btn btn-sm btn-light btn-icon"><i class="bi bi-pencil"></i></a>' : null;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-building', 'title' => __('No suppliers yet'), 'action' => auth()->user()->can('suppliers.manage') ? route('suppliers.create') : null, 'actionLabel' => __('Add supplier')];
    }
}
