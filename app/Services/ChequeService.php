<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cheques received (sales, customer payments) and issued (supplier
 * payments). A bounced cheque undoes its payment: the customer owes again,
 * or we owe the supplier again, and the invoices it paid reopen.
 */
class ChequeService
{
    public function __construct(protected CustomerLedgerService $customers, protected SupplierLedgerService $suppliers) {}

    /** @param array{number?: ?string, bank?: ?string, cheque_date?: ?string} $details */
    public function record(string $direction, array $details, string $amount, int $branchId, Model $payable, ?Customer $customer = null, ?Supplier $supplier = null): Cheque
    {
        if (empty($details['number'])) {
            throw new BusinessRuleException(__('Enter the cheque number.'));
        }

        return Cheque::withoutGlobalScopes()->create([
            'branch_id' => $branchId,
            'direction' => $direction,
            'number' => trim((string) $details['number']),
            'bank' => $details['bank'] ?? null,
            'cheque_date' => $details['cheque_date'] ?? today()->toDateString(),
            'amount' => Money::round($amount),
            'customer_id' => $customer?->id,
            'supplier_id' => $supplier?->id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
        ]);
    }

    public function clear(Cheque $cheque, User $user): Cheque
    {
        return $this->transition($cheque, 'cleared', $user, fn () => null);
    }

    public function cancel(Cheque $cheque, User $user): Cheque
    {
        return $this->transition($cheque, 'cancelled', $user, fn () => null);
    }

    public function bounce(Cheque $cheque, User $user, ?string $note = null): Cheque
    {
        return $this->transition($cheque, 'bounced', $user, fn (Cheque $locked) => $this->reverse($locked), $note);
    }

    /** A voided sale's cheques are cancelled (the sale no longer exists to be paid). */
    public function cancelForSale(Sale $sale, User $user): void
    {
        $paymentIds = $sale->payments()->pluck('id');
        Cheque::withoutGlobalScopes()->where('payable_type', (new SalePayment)->getMorphClass())->whereIn('payable_id', $paymentIds)
            ->where('status', 'pending')->update(['status' => 'cancelled', 'status_at' => now(), 'status_by' => $user->id]);
    }

    protected function transition(Cheque $cheque, string $status, User $user, callable $effect, ?string $note = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $status, $user, $effect, $note) {
            $locked = Cheque::withoutGlobalScopes()->lockForUpdate()->findOrFail($cheque->id);
            if ($locked->status !== 'pending' && ! ($locked->status === 'cleared' && $status === 'bounced')) {
                throw new BusinessRuleException(__('This cheque is already :s.', ['s' => __($locked->status)]));
            }
            $effect($locked);
            $locked->update(['status' => $status, 'status_at' => now(), 'status_by' => $user->id, 'note' => $note ?? $locked->note]);
            activity('cheques')->causedBy($user)->performedOn($locked)->withProperties(['number' => $locked->number, 'amount' => $locked->amount])->log("Cheque $status");

            return $locked;
        });
    }

    protected function reverse(Cheque $cheque): void
    {
        $payable = $cheque->payable()->withoutGlobalScopes()->first();
        $note = __('Cheque :n bounced', ['n' => $cheque->number]);

        if ($payable instanceof SalePayment) {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->find($payable->sale_id);
            if ($sale) {
                $sale->update(['balance_due' => Money::add($sale->balance_due, $cheque->amount), 'paid_total' => Money::sub($sale->paid_total, $cheque->amount)]);
                if ($sale->customer_id && ($customer = Customer::withTrashed()->find($sale->customer_id))) {
                    $this->customers->post($customer, 'cheque_bounced', $cheque->amount, 0, $cheque, $note, dueDate: today()->toDateString(), branchId: $cheque->branch_id);
                }
            }
        } elseif ($payable instanceof CustomerPayment) {
            foreach ($payable->allocations as $allocation) {
                $sale = Sale::withoutGlobalScopes()->lockForUpdate()->find($allocation->sale_id);
                $sale?->update(['balance_due' => Money::add($sale->balance_due, $allocation->amount), 'paid_total' => Money::sub($sale->paid_total, $allocation->amount)]);
            }
            $this->customers->post(Customer::withTrashed()->findOrFail($payable->customer_id), 'cheque_bounced', $cheque->amount, 0, $cheque, $note,
                dueDate: today()->toDateString(), branchId: $cheque->branch_id);
        } elseif ($payable instanceof SupplierPayment) {
            foreach ($payable->allocations as $allocation) {
                $bill = SupplierBill::withoutGlobalScopes()->lockForUpdate()->find($allocation->supplier_bill_id);
                if ($bill) {
                    $bill->paid = Money::max('0.00', Money::sub($bill->paid, $allocation->amount));
                    $bill->refreshStatus();
                    $bill->save();
                }
            }
            $this->suppliers->post(Supplier::withTrashed()->findOrFail($payable->supplier_id), 'cheque_bounced', 0, $cheque->amount, $cheque, $note, $cheque->branch_id);
        }
    }
}
