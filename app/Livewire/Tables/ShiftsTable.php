<?php

namespace App\Livewire\Tables;

use App\Models\Shift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class ShiftsTable extends DataTable
{
    protected string $defaultSort = 'opened_at';

    protected function title(): string
    {
        return __('Shifts');
    }

    protected function query(): Builder
    {
        return Shift::query()->with(['user', 'register', 'branch'])
            ->when(! auth()->user()->can('shifts.manage'), fn ($q) => $q->where('user_id', auth()->id()));
    }

    protected function searchable(): array
    {
        return ['number', 'user.name'];
    }

    protected function filterDefinitions(): array
    {
        $filters = [
            Filter::select('status', __('Statuses'), ['open' => __('Open'), 'closed' => __('Closed')]),
            Filter::dateRange('opened_at', __('Date')),
        ];
        if (auth()->user()->can('shifts.manage')) {
            array_unshift($filters, Filter::select('user_id', __('Cashiers'), User::query()->orderBy('name')->pluck('name', 'id')->all()));
        }

        return $filters;
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Shift'), 'number')->sortable()->html(fn ($s) => '<span class="font-monospace">'.e($s->number).'</span>')->exportAs(fn ($s) => $s->number),
            Column::make(__('Cashier'))->format(fn ($s) => $s->user?->name),
            Column::make(__('Till'))->format(fn ($s) => $s->register?->name.' · '.$s->branch?->code),
            Column::make(__('Opened'), 'opened_at')->sortable()->date(true),
            Column::make(__('Closed'), 'closed_at')->sortable()->date(true),
            Column::make(__('Expected'), 'expected_cash')->money(),
            Column::make(__('Counted'), 'counted_cash')->money(),
            Column::make(__('Over/short'), 'over_short')->sortable()->money()->html(fn ($s) => $s->over_short === null ? '—'
                : '<span class="fw-semibold '.(Money::isNegative($s->over_short) ? 'text-danger' : (Money::isZero($s->over_short) ? 'text-body-secondary' : 'text-success')).'">'.e(money($s->over_short)).'</span>'),
            Column::make(__('Status'), 'status')->html(fn ($s) => $s->status === 'open'
                ? '<span class="badge rounded-pill text-bg-success-soft status-badge">'.e(__('Open')).'</span>'
                : '<span class="badge rounded-pill text-bg-secondary-soft status-badge">'.e(__('Closed')).($s->force_closed ? ' · '.e(__('forced')) : '').'</span>')
                ->exportAs(fn ($s) => $s->status),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('shifts.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-cash-coin', 'title' => __('No shifts yet'), 'message' => __('Shifts are opened from the POS screen.')];
    }
}
