<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Livewire\Tables\Filter;
use App\Models\Platform\TenantLogin;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

/** Every shop user on the platform, from the central sign-in index. */
class LoginsTable extends AdminDataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Users');
    }

    protected function query(): Builder
    {
        return TenantLogin::query()->with('tenant');
    }

    protected function searchable(): array
    {
        return ['name', 'email', 'phone', 'tenant.name'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::boolean('is_active', __('Active'))];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->html(fn ($l) => '<div class="fw-semibold">'.e($l->name).'</div><div class="small text-body-secondary">'.e($l->email).'</div>')->exportAs(fn ($l) => $l->name),
            Column::make(__('Email'), 'email')->exportOnly(),
            Column::make(__('Phone'), 'phone')->format(fn ($l) => $l->phone ? PhoneNumber::display($l->phone) : '—'),
            Column::make(__('Business'), 'tenant.name')->html(fn ($l) => $l->tenant ? '<a href="'.route('admin.tenants.show', $l->tenant_id).'#users" class="text-reset">'.e($l->tenant->name).'</a>' : '—')->exportAs(fn ($l) => $l->tenant?->name),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('admin.tenants.show', $row->tenant_id).'#users';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-people', 'title' => __('No users found')];
    }
}
