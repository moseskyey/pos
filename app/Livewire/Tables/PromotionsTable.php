<?php

namespace App\Livewire\Tables;

use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\ComponentAttributeBag;

class PromotionsTable extends DataTable
{
    protected string $defaultSort = 'priority';

    protected function title(): string
    {
        return __('Promotions');
    }

    protected function query(): Builder
    {
        return Promotion::query()->withCount('targets');
    }

    protected function searchable(): array
    {
        return ['name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('type', __('Type'), [
                'percent' => __('% off'), 'amount' => __('Amount off'), 'buy_get' => __('Buy X get Y'), 'multi_price' => __('N for a price'),
            ]),
            Filter::boolean('is_active', __('Switched on')),
        ];
    }

    protected function columns(): array
    {
        $running = app(PromotionService::class)->running(branch_context()->currentId())->pluck('id')->all();

        return [
            Column::make(__('Promotion'), 'name')->sortable()->html(fn (Promotion $p) => '<div class="fw-semibold">'.e($p->name).'</div><div class="small text-success">'.e($p->summary()).'</div>')
                ->exportAs(fn (Promotion $p) => $p->name),
            Column::make(__('Applies to'))->format(fn (Promotion $p) => match ($p->applies_to) {
                'products' => trans_choice(':count product|:count products', $p->targets_count),
                'categories' => trans_choice(':count category|:count categories', $p->targets_count),
                default => __('Everything'),
            }),
            Column::make(__('When'))->format(fn (Promotion $p) => $this->when($p)),
            Column::make(__('Status'))->html(fn (Promotion $p) => view('components.status-badge', [
                'status' => ! $p->is_active ? 'inactive' : (in_array($p->id, $running, true) ? 'active' : 'pending'),
                'label' => ! $p->is_active ? __('Off') : (in_array($p->id, $running, true) ? __('Running now') : __('Scheduled')),
                'attributes' => new ComponentAttributeBag,
            ])->render())->exportAs(fn (Promotion $p) => $p->is_active ? __('On') : __('Off')),
        ];
    }

    protected function when(Promotion $p): string
    {
        $parts = [];
        if ($p->starts_on || $p->ends_on) {
            $parts[] = ($p->starts_on ? format_date($p->starts_on) : '…').' – '.($p->ends_on ? format_date($p->ends_on) : '…');
        }
        if ($p->days_of_week) {
            $parts[] = collect($p->days_of_week)->sort()->map(fn ($d) => now()->startOfWeek()->addDays($d - 1)->translatedFormat('D'))->join(', ');
        }
        if ($p->start_time && $p->end_time) {
            $parts[] = substr($p->start_time, 0, 5).'–'.substr($p->end_time, 0, 5);
        }

        return $parts ? implode(' · ', $parts) : __('Always');
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('promotions.edit', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-megaphone', 'title' => __('No promotions yet'), 'message' => __('Create a promotion and it applies at the till automatically.'),
            'action' => route('promotions.create'), 'actionLabel' => __('New promotion')];
    }
}
