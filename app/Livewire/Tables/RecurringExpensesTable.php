<?php

namespace App\Livewire\Tables;

use App\Enums\PaymentMethod;
use App\Livewire\Concerns\ModalForm;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class RecurringExpensesTable extends DataTable
{
    use ModalForm;

    protected string $defaultSort = 'next_run_date';

    protected string $defaultDirection = 'asc';

    protected ?string $formView = 'livewire.forms.recurring-expense';

    protected function title(): string
    {
        return __('Recurring expenses');
    }

    protected function query(): Builder
    {
        return RecurringExpense::query()->with('category');
    }

    protected function searchable(): array
    {
        return ['description'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Description'), 'description')->sortable(),
            Column::make(__('Category'))->format(fn ($r) => $r->category?->name),
            Column::make(__('Frequency'), 'frequency')->format(fn ($r) => $r->frequency === 'weekly' ? __('Weekly') : __('Monthly')),
            Column::make(__('Next run'), 'next_run_date')->sortable()->date(),
            Column::make(__('Amount'), 'amount')->sortable()->money(),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        return '<button class="btn btn-sm btn-light btn-icon" wire:click="openEdit('.$row->id.')"><i class="bi bi-pencil"></i></button>';
    }

    protected function authorizeForm(): void
    {
        abort_unless(auth()->user()->can('expenses.manage'), 403);
    }

    protected function formFields(): array
    {
        return ['description' => '', 'expense_category_id' => null, 'amount' => null, 'payment_method' => 'bank', 'frequency' => 'monthly', 'next_run_date' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(), 'is_active' => true];
    }

    protected function formRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'frequency' => ['required', Rule::in(['monthly', 'weekly'])],
            'next_run_date' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    protected function findRecord(int $id): mixed
    {
        return RecurringExpense::findOrFail($id);
    }

    protected function persist(array $data): void
    {
        if ($this->editingId) {
            RecurringExpense::findOrFail($this->editingId)->update($data);
        } else {
            $branchId = branch_context()->currentId();
            abort_unless($branchId, 422, __('Select a branch first.'));
            RecurringExpense::create($data + ['branch_id' => $branchId, 'created_by' => auth()->id()]);
        }
    }

    public function render()
    {
        return parent::render()->with([
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => collect(PaymentMethod::cases())->reject(fn ($m) => $m->isAccount())->mapWithKeys(fn ($m) => [$m->value => $m->label()]),
        ]);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-arrow-repeat', 'title' => __('No recurring expenses'), 'message' => __('Set up rent, salaries or LUKU so they are recorded automatically.')];
    }
}
