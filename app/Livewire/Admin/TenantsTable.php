<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Livewire\Tables\Filter;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable extends AdminDataTable
{
    protected function title(): string
    {
        return __('Businesses');
    }

    protected function query(): Builder
    {
        return Tenant::query()->with('plan')->when(($this->filters['status'] ?? null) === 'deleted', fn ($q) => $q->onlyTrashed());
    }

    protected function searchable(): array
    {
        return ['name', 'slug', 'owner_name', 'owner_email', 'owner_phone'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Status'), Tenant::statusLabels() + ['deleted' => __('Deleted')])
                ->query(fn ($q, $v) => $v === 'deleted' ? $q : $q->whereStatus($v)),
            Filter::select('plan_id', __('Plan'), Plan::orderBy('sort_order')->pluck('name', 'id')->all()),
            Filter::dateRange('created_at', __('Signed up')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Business'), 'name')->sortable()
                ->html(fn (Tenant $t) => '<div class="fw-semibold">'.e($t->name).'</div><div class="small text-body-secondary">#'.$t->id.' · '.e($t->slug).'</div>')
                ->exportAs(fn (Tenant $t) => $t->name),
            Column::make(__('Owner'), 'owner_name')
                ->html(fn (Tenant $t) => '<div>'.e($t->owner_name).'</div><div class="small text-body-secondary">'.e($t->owner_phone ? PhoneNumber::display($t->owner_phone) : $t->owner_email).'</div>')
                ->exportAs(fn (Tenant $t) => trim($t->owner_name.' '.$t->owner_email.' '.$t->owner_phone)),
            Column::make(__('Plan'), 'plan.name')->format(fn (Tenant $t) => $t->plan?->name ?? '—'),
            Column::make(__('Status'))
                ->html(fn (Tenant $t) => $t->trashed()
                    ? '<span class="badge rounded-pill text-bg-danger-soft status-badge">'.e(__('Deleted')).'</span>'
                    : '<span class="badge rounded-pill text-bg-'.Tenant::statusColor($t->status()).'-soft status-badge">'.e(Tenant::statusLabels()[$t->status()]).'</span>')
                ->exportAs(fn (Tenant $t) => $t->trashed() ? __('Deleted') : Tenant::statusLabels()[$t->status()]),
            Column::make(__('Access until'), 'paid_until')->sortable()
                ->format(fn (Tenant $t) => $t->accessEndsAt()?->format('d/m/Y') ?? '—')->classes('text-nowrap'),
            Column::make(__('Last active'), 'last_activity_at')->sortable()
                ->format(fn (Tenant $t) => $t->last_activity_at?->diffForHumans() ?? __('Never'))->classes('text-nowrap small'),
            Column::make(__('Signed up'), 'created_at')->sortable()->date(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('admin.tenants.show', $row->id);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-shop', 'title' => __('No businesses found'), 'message' => __('Try changing your search or filters.'), 'action' => route('admin.tenants.create'), 'actionLabel' => __('Add business')];
    }
}
