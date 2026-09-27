<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Models\Platform\Plan;
use Illuminate\Database\Eloquent\Builder;

class PlansTable extends AdminDataTable
{
    protected string $defaultSort = 'sort_order';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Plans');
    }

    protected function query(): Builder
    {
        return Plan::query()->withCount('tenants');
    }

    protected function searchable(): array
    {
        return ['name', 'slug'];
    }

    protected function columns(): array
    {
        $limit = fn (?int $v) => $v === null ? '∞' : number_format($v);

        return [
            Column::make(__('Plan'), 'name')->sortable()->html(fn (Plan $p) => '<div class="fw-semibold">'.e($p->name).'</div><div class="small text-body-secondary">'.e($p->description).'</div>')->exportAs(fn ($p) => $p->name),
            Column::make(__('Price'), 'price')->sortable()->html(fn (Plan $p) => '<span class="fw-semibold">'.e(money($p->price)).'</span> <span class="small text-body-secondary">/ '.e($p->intervalLabel()).'</span>')->exportAs(fn ($p) => $p->price),
            Column::make(__('Branches'), 'max_branches')->format(fn (Plan $p) => $limit($p->max_branches))->align('end'),
            Column::make(__('Users'), 'max_users')->format(fn (Plan $p) => $limit($p->max_users))->align('end'),
            Column::make(__('Products'), 'max_products')->format(fn (Plan $p) => $limit($p->max_products))->align('end'),
            Column::make(__('Businesses'), 'tenants_count')->sortable()->number(),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('admin.plans.edit', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-stars', 'title' => __('No plans yet'), 'message' => __('Create the plans businesses can subscribe to.'), 'action' => route('admin.plans.create'), 'actionLabel' => __('Add plan')];
    }
}
