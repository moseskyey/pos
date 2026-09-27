<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Models\Platform\Announcement;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementsTable extends AdminDataTable
{
    protected function title(): string
    {
        return __('Announcements');
    }

    protected function query(): Builder
    {
        return Announcement::query()->with('tenant');
    }

    protected function searchable(): array
    {
        return ['title', 'body'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Title'), 'title')->sortable()->html(fn ($a) => '<span class="badge rounded-pill text-bg-'.e($a->level).'-soft me-1">&nbsp;</span><span class="fw-semibold">'.e($a->title).'</span><div class="small text-body-secondary text-truncate" style="max-width:420px">'.e($a->body).'</div>')->exportAs(fn ($a) => $a->title),
            Column::make(__('Audience'), 'tenant_id')->format(fn ($a) => $a->tenant?->name ?? __('All businesses')),
            Column::make(__('From'), 'starts_at')->sortable()->date(true),
            Column::make(__('Until'), 'ends_at')->sortable()->date(true),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('admin.announcements.edit', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-megaphone', 'title' => __('No announcements'), 'message' => __('Post a notice that every business sees at the top of their pages.'), 'action' => route('admin.announcements.create'), 'actionLabel' => __('New announcement')];
    }
}
