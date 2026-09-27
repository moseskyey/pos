<?php

namespace App\Livewire\Tables;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class SalesTable extends DataTable
{
    #[Locked]
    public ?int $customerId = null;

    #[Locked]
    public ?string $onlyStatus = null;

    protected function title(): string
    {
        return __('Sales');
    }

    protected function query(): Builder
    {
        $user = auth()->user();

        return Sale::query()->with(['customer', 'cashier', 'branch', 'payments'])
            ->whereIn('status', $this->onlyStatus ? [$this->onlyStatus] : [SaleStatus::Completed->value, SaleStatus::Voided->value, SaleStatus::Layaway->value])
            ->when($this->customerId, fn ($q) => $q->where('customer_id', $this->customerId))
            ->when(! $user->can('sales.view_all'), fn ($q) => $q->where('user_id', $user->id));
    }

    protected function searchable(): array
    {
        return ['number', 'customer.name', 'note'];
    }

    protected function filterDefinitions(): array
    {
        $filters = [Filter::dateRange('created_at', __('Date'))];
        if (! $this->onlyStatus) {
            $filters[] = Filter::select('status', __('Statuses'), SaleStatus::options());
        }
        if (auth()->user()->can('sales.view_all')) {
            $filters[] = Filter::select('user_id', __('Cashiers'), User::query()->orderBy('name')->pluck('name', 'id')->all());
        }
        $filters[] = Filter::select('origin', __('Offline sales'), ['offline' => __('Made offline'), 'review' => __('Needs review')])
            ->query(fn ($q, $v) => $v === 'review' ? $q->whereNotNull('review_flags') : $q->whereNotNull('synced_at'));
        $filters[] = Filter::select('method', __('Payment methods'), PaymentMethod::options())
            ->query(fn ($q, $v) => $q->whereHas('payments', fn ($p) => $p->where('method', $v)));
        if (! $this->customerId) {
            $filters[] = Filter::select('balance', __('Balance'), ['due' => __('With balance due'), 'paid' => __('Fully paid')])
                ->query(fn ($q, $v) => $v === 'due' ? $q->where('balance_due', '>', 0) : $q->where('balance_due', '<=', 0));
        }

        return $filters;
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Receipt'), 'number')->sortable()->html(fn ($s) => '<span class="font-monospace">'.e($s->number).'</span>')->exportAs(fn ($s) => $s->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Branch'))->format(fn ($s) => $s->branch?->code)->visible(count(branch_context()->activeIds()) > 1),
            Column::make(__('Customer'))->visible(! $this->customerId)->format(fn ($s) => $s->customer?->name ?? __('Walk-in')),
            Column::make(__('Cashier'))->format(fn ($s) => $s->cashier?->name),
            Column::make(__('Payment'))->format(fn ($s) => $s->payments->map(fn ($p) => $p->method->label())->unique()->join(', ') ?: '—'),
            Column::make(__('Total'), 'total')->sortable()->money()->total(),
            Column::make(__('Balance'), 'balance_due')->sortable()->money()->total()->html(fn ($s) => $s->balance_due > 0 ? '<span class="text-danger fw-semibold">'.e(money($s->balance_due)).'</span>' : '<span class="text-body-secondary">—</span>'),
            Column::make(__('Due'), 'due_date')->sortable()->visible($this->customerId !== null && feature('credit_terms'))
                ->html(fn ($s) => $s->due_date && $s->balance_due > 0
                    ? '<span class="'.($s->isOverdue() ? 'text-danger fw-semibold' : '').'">'.e(format_date($s->due_date)).'</span>'
                    : '<span class="text-body-secondary">—</span>')
                ->exportAs(fn ($s) => $s->due_date ? format_date($s->due_date) : ''),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('sales.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-receipt', 'title' => __('No sales found'), 'message' => __('Completed sales from the POS appear here.')];
    }
}
