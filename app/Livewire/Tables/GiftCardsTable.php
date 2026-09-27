<?php

namespace App\Livewire\Tables;

use App\Models\GiftCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\ComponentAttributeBag;

class GiftCardsTable extends DataTable
{
    protected function title(): string
    {
        return __('Gift cards');
    }

    protected function query(): Builder
    {
        return GiftCard::query()->with('customer');
    }

    protected function searchable(): array
    {
        return ['code', 'note', 'customer.name'];
    }

    /** Codes are stored without dashes; accept them typed as printed (XXXX-XXXX-XXXX). */
    public function updatedSearch(): void
    {
        if (preg_match('/^[A-Za-z0-9]{4}(-[A-Za-z0-9]{1,4}){1,2}$/', trim($this->search))) {
            $this->search = GiftCard::normalizeCode($this->search);
        }
        parent::updatedSearch();
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('kind', __('Type'), ['gift_card' => __('Gift card'), 'voucher' => __('Voucher')]),
            Filter::select('state', __('Status'), ['usable' => __('Usable'), 'used' => __('Used up'), 'expired' => __('Expired'), 'inactive' => __('Deactivated')])
                ->query(fn ($q, $v) => match ($v) {
                    'usable' => $q->where('is_active', true)->where('balance', '>', 0)->where(fn ($w) => $w->whereNull('expires_on')->orWhereDate('expires_on', '>=', today())),
                    'used' => $q->where('balance', '<=', 0),
                    'expired' => $q->whereDate('expires_on', '<', today()),
                    'inactive' => $q->where('is_active', false),
                    default => $q,
                }),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Code'), 'code')->sortable()->html(fn (GiftCard $c) => '<span class="font-monospace fw-semibold">'.e($c->displayCode()).'</span>'
                .'<div class="small text-body-secondary">'.e($c->kind === 'voucher' ? __('Voucher') : __('Gift card')).'</div>')->exportAs(fn (GiftCard $c) => $c->displayCode()),
            Column::make(__('Customer'))->format(fn (GiftCard $c) => $c->customer?->name ?? '—'),
            Column::make(__('Issued'), 'created_at')->sortable()->date(),
            Column::make(__('Expires'), 'expires_on')->sortable()->format(fn (GiftCard $c) => $c->expires_on ? format_date($c->expires_on) : __('Never')),
            Column::make(__('Value'), 'initial_value')->sortable()->money(),
            Column::make(__('Balance'), 'balance')->sortable()->money()->total(),
            Column::make(__('Status'))->html(fn (GiftCard $c) => view('components.status-badge', ['status' => $c->status(), 'label' => null, 'attributes' => new ComponentAttributeBag])->render())
                ->exportAs(fn (GiftCard $c) => $c->status()),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('gift-cards.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-gift', 'title' => __('No gift cards yet'), 'action' => route('gift-cards.create'), 'actionLabel' => __('Issue gift card')];
    }
}
