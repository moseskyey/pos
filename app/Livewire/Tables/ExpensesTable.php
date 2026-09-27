<?php

namespace App\Livewire\Tables;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ExpensesTable extends DataTable
{
    protected string $defaultSort = 'expense_date';

    protected function title(): string
    {
        return __('Expenses');
    }

    protected function query(): Builder
    {
        return Expense::query()->with(['category', 'user', 'branch']);
    }

    protected function searchable(): array
    {
        return ['number', 'description', 'payee', 'reference'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('expense_category_id', __('Categories'), ExpenseCategory::orderBy('name')->pluck('name', 'id')->all()),
            Filter::select('payment_method', __('Methods'), PaymentMethod::options()),
            Filter::dateRange('expense_date', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Expense'), 'number')->sortable()->html(fn ($e) => '<span class="font-monospace">'.e($e->number).'</span>')->exportAs(fn ($e) => $e->number),
            Column::make(__('Date'), 'expense_date')->sortable()->date(),
            Column::make(__('Branch'))->format(fn ($e) => $e->branch?->code)->visible(count(branch_context()->activeIds()) > 1),
            Column::make(__('Category'))->html(fn ($e) => '<span class="badge rounded-pill text-bg-primary-soft">'.e($e->category?->name).'</span>')->exportAs(fn ($e) => $e->category?->name),
            Column::make(__('Description'), 'description')->format(fn ($e) => Str::limit(trim(($e->payee ? $e->payee.' · ' : '').$e->description), 50) ?: '—'),
            Column::make(__('Method'))->format(fn ($e) => (PaymentMethod::tryFrom($e->payment_method)?->label() ?? $e->payment_method).($e->paid_from_drawer ? ' ('.__('drawer').')' : '')),
            Column::make(__('Receipt'))->html(fn ($e) => $e->attachment_path ? '<a href="'.$e->attachmentUrl().'" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-paperclip"></i></a>' : '')->hideOnExport(),
            Column::make(__('Amount'), 'amount')->sortable()->money()->total(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        return auth()->user()->can('expenses.manage') ? '<a href="'.route('expenses.edit', $row).'" class="btn btn-sm btn-light btn-icon"><i class="bi bi-pencil"></i></a>' : null;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-credit-card-2-back', 'title' => __('No expenses recorded'), 'action' => auth()->user()->can('expenses.manage') ? route('expenses.create') : null, 'actionLabel' => __('Record expense')];
    }
}
