<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(protected DocumentNumberService $numbers, protected ShiftService $shifts) {}

    public function record(int $branchId, array $data, User $user, ?UploadedFile $attachment = null): Expense
    {
        return DB::transaction(function () use ($branchId, $data, $user, $attachment) {
            $shift = null;
            if (! empty($data['paid_from_drawer'])) {
                if (($data['payment_method'] ?? 'cash') !== PaymentMethod::Cash->value) {
                    throw new BusinessRuleException(__('Only cash expenses can be paid from the drawer.'));
                }
                $shift = $this->shifts->current($user, $branchId);
                if (! $shift) {
                    throw new BusinessRuleException(__('Open a shift to pay from the cash drawer.'));
                }
                if (Money::gt($data['amount'], $this->shifts->expectedCash($shift))) {
                    throw new BusinessRuleException(__('Not enough cash in the drawer.'));
                }
            }

            $expense = Expense::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'expense_category_id' => $data['expense_category_id'],
                'recurring_expense_id' => $data['recurring_expense_id'] ?? null,
                'shift_id' => $shift?->id,
                'number' => $this->numbers->next('expense', $branchId),
                'expense_date' => $data['expense_date'] ?? now()->toDateString(),
                'amount' => Money::round($data['amount']),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'paid_from_drawer' => (bool) $shift,
                'reference' => $data['reference'] ?? null,
                'payee' => $data['payee'] ?? null,
                'description' => $data['description'] ?? null,
                'user_id' => $user->id,
            ]);
            if ($attachment) {
                $expense->update(['attachment_path' => $attachment->store('expenses/'.now()->format('Y/m'), 'local')]);
            }

            return $expense;
        });
    }

    /** Create expenses from due recurring templates. Returns the count created. */
    public function runRecurring(?User $user = null): int
    {
        $count = 0;
        $due = RecurringExpense::withoutGlobalScopes()->where('is_active', true)->whereDate('next_run_date', '<=', today())->get();
        foreach ($due as $template) {
            $runner = $user ?? User::find($template->created_by);
            while ($template->next_run_date->lte(today())) {
                $this->record($template->branch_id, [
                    'expense_category_id' => $template->expense_category_id,
                    'recurring_expense_id' => $template->id,
                    'expense_date' => $template->next_run_date->toDateString(),
                    'amount' => $template->amount,
                    'payment_method' => $template->payment_method,
                    'description' => $template->description,
                ], $runner);
                $template->next_run_date = $template->frequency === 'weekly' ? $template->next_run_date->addWeek() : $template->next_run_date->addMonthNoOverflow();
                $count++;
            }
            $template->save();
        }

        return $count;
    }
}
