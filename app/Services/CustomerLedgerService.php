<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Customer accounts. "receivable" tracks what the customer owes (debit
 * increases); "store_credit" tracks what the shop owes the customer
 * (credit increases). Cached balances live on the customer row.
 */
class CustomerLedgerService
{
    public function post(
        Customer $customer,
        string $type,
        string|int|float $debit = 0,
        string|int|float $credit = 0,
        ?Model $reference = null,
        ?string $note = null,
        string $account = 'receivable',
        ?string $dueDate = null,
        ?int $branchId = null,
    ): CustomerLedgerEntry {
        return $this->transaction(function () use ($customer, $type, $debit, $credit, $reference, $note, $account, $dueDate, $branchId) {
            $locked = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            if ($account === 'store_credit') {
                $locked->store_credit = Money::add($locked->store_credit, Money::sub($credit, $debit));
                $balance = $locked->store_credit;
            } else {
                $locked->balance = Money::add($locked->balance, Money::sub($debit, $credit));
                $balance = $locked->balance;
            }
            $locked->save();
            $customer->setRawAttributes($locked->getAttributes(), true);

            return CustomerLedgerEntry::create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId ?? $reference?->branch_id,
                'account' => $account,
                'type' => $type,
                'debit' => Money::round($debit),
                'credit' => Money::round($credit),
                'balance_after' => $balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'due_date' => $dueDate,
                'note' => $note,
                'user_id' => Auth::id(),
            ]);
        });
    }

    public function openingBalance(Customer $customer, string|int|float $amount): void
    {
        if (Money::isPositive($amount)) {
            $this->post($customer, 'opening', $amount, 0, null, __('Opening balance'));
        }
    }

    protected function transaction(callable $callback): mixed
    {
        return DB::transactionLevel() > 0 ? $callback() : DB::transaction($callback);
    }
}
