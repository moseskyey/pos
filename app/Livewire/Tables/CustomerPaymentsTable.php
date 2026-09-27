<?php

namespace App\Livewire\Tables;

use App\Enums\PaymentMethod;
use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Builder;

class CustomerPaymentsTable extends DataTable
{
    protected function title(): string
    {
        return __('Customer payments');
    }

    protected function query(): Builder
    {
        return CustomerPayment::query()->with(['customer', 'user']);
    }

    protected function searchable(): array
    {
        return ['number', 'reference', 'customer.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('method', __('Methods'), PaymentMethod::options()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Receipt'), 'number')->sortable()->html(fn ($p) => '<span class="font-monospace">'.e($p->number).'</span>')->exportAs(fn ($p) => $p->number),
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Customer'))->html(fn ($p) => '<a href="'.route('customers.show', $p->customer_id).'" class="text-decoration-none">'.e($p->customer?->name).'</a>')->exportAs(fn ($p) => $p->customer?->name),
            Column::make(__('Method'))->format(fn ($p) => $p->method->label()),
            Column::make(__('Reference'), 'reference'),
            Column::make(__('Amount'), 'amount')->sortable()->money()->total(),
            Column::make(__('Received by'))->format(fn ($p) => $p->user?->name),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('customer-payments.show', $row);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-wallet2', 'title' => __('No payments yet'), 'action' => route('customer-payments.create'), 'actionLabel' => __('Receive payment')];
    }
}
