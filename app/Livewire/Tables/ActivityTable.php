<?php

namespace App\Livewire\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityTable extends DataTable
{
    protected function title(): string
    {
        return __('Activity log');
    }

    protected function query(): Builder
    {
        return Activity::query()->with(['causer']);
    }

    protected function searchable(): array
    {
        return ['description', 'log_name', 'subject_type'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('log_name', __('Logs'), Activity::query()->whereNotNull('log_name')->distinct()->pluck('log_name', 'log_name')->map(fn ($l) => ucfirst($l))->all()),
            Filter::select('causer_id', __('Users'), User::withTrashed()->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn ($q, $v) => $q->where('causer_id', $v)->where('causer_type', (new User)->getMorphClass())),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('When'), 'created_at')->sortable()->date(true),
            Column::make(__('User'))->format(fn ($a) => $a->causer?->name ?? __('System')),
            Column::make(__('Log'), 'log_name')->html(fn ($a) => '<span class="badge rounded-pill text-bg-secondary-soft">'.e($a->log_name).'</span>')->exportAs(fn ($a) => $a->log_name),
            Column::make(__('Action'), 'description')->html(fn ($a) => '<span class="fw-medium">'.e($a->description).'</span>'.($a->event ? ' <span class="badge text-bg-info-soft">'.e($a->event).'</span>' : ''))->exportAs(fn ($a) => $a->description),
            Column::make(__('Subject'))->format(fn ($a) => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '—'),
            Column::make(__('Details'))->view('activity.details')->exportAs(fn ($a) => json_encode($a->attribute_changes ?: $a->properties)),
        ];
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-clock-history', 'title' => __('No activity yet')];
    }
}
