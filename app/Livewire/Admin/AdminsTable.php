<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Database\Eloquent\Builder;

class AdminsTable extends AdminDataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    public function boot(): void
    {
        parent::boot();
        abort_unless(auth('admin')->user()->is_super, 403);
    }

    protected function title(): string
    {
        return __('Admins');
    }

    protected function query(): Builder
    {
        return PlatformAdmin::query();
    }

    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->html(fn ($a) => '<div class="fw-semibold">'.e($a->name).'</div><div class="small text-body-secondary">'.e($a->email).'</div>')->exportAs(fn ($a) => $a->name),
            Column::make(__('Role'), 'is_super')->format(fn ($a) => $a->is_super ? __('Super admin') : __('Support admin')),
            Column::make(__('Last sign in'), 'last_login_at')->sortable()->date(true),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('admin.admins.edit', $row);
    }
}
