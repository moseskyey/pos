<?php

namespace App\Livewire\Tables;

use App\Models\Branch;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

class BranchesTable extends DataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Branches');
    }

    protected function query(): Builder
    {
        $user = auth()->user();

        return Branch::query()->withCount(['users', 'registers'])
            ->when(! $user->can('branches.view_all'), fn ($q) => $q->whereIn('id', $user->branches()->pluck('branches.id')));
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'address', 'phone'];
    }

    protected function filterDefinitions(): array
    {
        return [Filter::boolean('is_active', __('Active'))];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Branch'), 'name')->sortable(),
            Column::make(__('Code'), 'code')->sortable()->html(fn ($b) => '<span class="badge text-bg-secondary-soft font-monospace">'.e($b->code).'</span>'),
            Column::make(__('Address'), 'address'),
            Column::make(__('Phone'), 'phone')->format(fn ($b) => PhoneNumber::display($b->phone) ?: '—'),
            Column::make(__('Tills'), 'registers_count')->number(),
            Column::make(__('Users'), 'users_count')->number(),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('branches.show', $row);
    }

    protected function rowActions(mixed $row): ?string
    {
        if (! auth()->user()->can('branches.manage')) {
            return null;
        }

        return '<a href="'.route('branches.edit', $row).'" class="btn btn-sm btn-light btn-icon" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></a>';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-shop', 'title' => __('No branches yet'), 'message' => __('Add your first shop location.'),
            'action' => route('branches.create'), 'actionLabel' => __('Add branch')];
    }
}
