<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Livewire\Tables\Filter;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Database\Eloquent\Builder;

class ActivityTable extends AdminDataTable
{
    public ?int $tenantId = null;

    protected bool $exportable = true;

    protected function title(): string
    {
        return __('Admin activity');
    }

    protected function query(): Builder
    {
        return AdminActivity::query()->with(['admin', 'tenant'])->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId));
    }

    protected function searchable(): array
    {
        return ['description', 'action', 'ip'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('admin_id', __('Admin'), PlatformAdmin::orderBy('name')->pluck('name', 'id')->all()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('When'), 'created_at')->sortable()->date(true),
            Column::make(__('Admin'), 'admin.name')->format(fn ($a) => $a->admin?->name ?? __('System')),
            Column::make(__('Business'), 'tenant.name')->visible(! $this->tenantId)
                ->html(fn ($a) => $a->tenant ? '<a href="'.route('admin.tenants.show', $a->tenant_id).'" class="text-reset">'.e($a->tenant->name).'</a>' : '—')
                ->exportAs(fn ($a) => $a->tenant?->name),
            Column::make(__('Action'), 'description')->html(fn ($a) => '<div>'.e($a->description).'</div><div class="small text-body-secondary font-monospace">'.e($a->action).'</div>')->exportAs(fn ($a) => $a->description),
            Column::make(__('IP'), 'ip')->classes('small text-body-secondary'),
        ];
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-clock-history', 'title' => __('No activity yet')];
    }
}
