<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Receive payments against customer debt, allocated FIFO to open credit invoices.
 */
class CustomerPaymentService
{
    public function __construct(
        protected CustomerLedgerService $ledger,
        protected DocumentNumberService $numbers,
        protected ShiftService $shifts,
    ) {}

    public function receive(Customer $customer, string|int|float $amount, PaymentMethod $method, User $user, int $branchId, ?string $reference = null, ?string $note = null, ?string $idempotencyKey = null): CustomerPayment
    {
        if ($idempotencyKey && ($existing = CustomerPayment::withoutGlobalScopes()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException(__('Enter an amount greater than zero.'));
        }
        if ($method->isAccount()) {
            throw new BusinessRuleException(__('Choose a real payment method.'));
        }
        if ($method->isMobileMoney() && ! $reference) {
            throw new BusinessRuleException(__('Enter the mobile money transaction reference.'));
        }

        return DB::transaction(function () use ($customer, $amount, $method, $user, $branchId, $reference, $note, $idempotencyKey) {
            $shift = $method === PaymentMethod::Cash ? $this->shifts->current($user, $branchId) : null;
            $payment = CustomerPayment::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'number' => $this->numbers->next('customer_payment', $branchId),
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'idempotency_key' => $idempotencyKey,
            ]);

            $remaining = $amount;
            $open = Sale::withoutGlobalScopes()->where('customer_id', $customer->id)
                ->where('status', SaleStatus::Completed->value)->where('balance_due', '>', 0)
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($open as $sale) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $apply = Money::min($remaining, $sale->balance_due);
                $payment->allocations()->create(['sale_id' => $sale->id, 'amount' => $apply]);
                $sale->update(['balance_due' => Money::sub($sale->balance_due, $apply), 'paid_total' => Money::add($sale->paid_total, $apply)]);
                $remaining = Money::sub($remaining, $apply);
            }

            $this->ledger->post($customer, 'payment', 0, $amount, $payment, __('Payment :n (:m)', ['n' => $payment->number, 'm' => $method->label()]), branchId: $branchId);

            // Overpayment beyond all debt becomes store credit.
            $fresh = $customer->fresh();
            if (Money::isNegative($fresh->balance)) {
                $excess = Money::abs($fresh->balance);
                $this->ledger->post($fresh, 'transfer', $excess, 0, $payment, __('Overpayment moved to store credit'), branchId: $branchId);
                $this->ledger->post($fresh, 'overpayment', 0, $excess, $payment, __('Overpayment on :n', ['n' => $payment->number]), 'store_credit', branchId: $branchId);
            }

            activity('customers')->causedBy($user)->performedOn($payment)->withProperties(['customer' => $customer->name, 'amount' => $amount])->log('Customer payment received');

            return $payment;
        });
    }
}
