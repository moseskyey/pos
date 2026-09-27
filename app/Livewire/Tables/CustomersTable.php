<?php

namespace App\Livewire\Tables;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable extends DataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Customers');
    }

    protected function query(): Builder
    {
        return Customer::query()->withCount(['sales' => fn ($q) => $q->where('status', 'completed')]);
    }

    protected function searchable(): array
    {
        return ['name', 'phone', 'email', 'tin'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('type', __('Types'), ['retail' => __('Retail'), 'wholesale' => __('Wholesale')]),
            Filter::select('debt', __('Balance'), ['owing' => __('Owing money'), 'over' => __('Over credit limit'), 'credit' => __('Has store credit')])
                ->query(fn ($q, $v) => match ($v) {
                    'owing' => $q->where('balance', '>', 0),
                    'over' => $q->whereColumn('balance', '>', 'credit_limit')->where('balance', '>', 0),
                    default => $q->where('store_credit', '>', 0),
                }),
            Filter::boolean('is_active', __('Active')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Customer'), 'name')->sortable()->html(fn ($c) => '<div class="d-flex align-items-center gap-2"><span class="avatar avatar-sm">'.e(strtoupper(mb_substr($c->name, 0, 2))).'</span><div><div class="fw-semibold">'.e($c->name).'</div><div class="small text-body-secondary">'.e($c->displayPhone()).'</div></div></div>')->exportAs(fn ($c) => $c->name),
            Column::make(__('Phone'), 'phone')->exportOnly()->exportAs(fn ($c) => $c->displayPhone()),
            Column::make(__('Type'), 'type')->html(fn ($c) => '<span class="badge rounded-pill text-bg-'.($c->isWholesale() ? 'info' : 'secondary').'-soft">'.e($c->isWholesale() ? __('Wholesale') : __('Retail')).'</span>')->exportAs(fn ($c) => $c->type),
            Column::make(__('Visits'), 'sales_count')->sortable()->number(),
            Column::make(__('Credit limit'), 'credit_limit')->sortable()->money(),
            Column::make(__('Balance'), 'balance')->sortable()->money()->total()->html(fn ($c) => $c->balance > 0
                ? '<span class="fw-semibold '.($c->balance > $c->credit_limit ? 'text-danger' : 'text-warning').'">'.e(money($c->balance)).'</span>'
                : '<span class="text-body-secondary">—</span>'),
            Column::make(__('Store credit'), 'store_credit')->sortable()->money(),
            Column::make(__('Points'), 'loyalty_points')->sortable()->number()->visible((bool) setting('loyalty.enabled')),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('customers.show', $row);
    }

    protected function rowActions(mixed $row): ?string
    {
        $html = '';
        if ($row->balance > 0 && auth()->user()->can('customers.payments')) {
            $html .= '<a href="'.route('customer-payments.create', ['customer' => $row->id]).'" class="btn btn-sm btn-soft-primary" title="'.e(__('Receive payment')).'"><i class="bi bi-cash"></i></a> ';
        }
        if (auth()->user()->can('customers.manage')) {
            $html .= '<a href="'.route('customers.edit', $row).'" class="btn btn-sm btn-light btn-icon" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></a>';
        }

        return $html;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-people', 'title' => __('No customers yet'), 'message' => __('Register customers for credit sales, loyalty points and statements.'),
            'action' => auth()->user()->can('customers.manage') ? route('customers.create') : null, 'actionLabel' => __('Add customer')];
    }
}
