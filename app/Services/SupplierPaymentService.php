<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(protected SupplierLedgerService $ledger, protected DocumentNumberService $numbers, protected ShiftService $shifts) {}

    /** @param array<int, mixed> $allocations bill_id => amount (empty = FIFO) */
    public function pay(Supplier $supplier, string|int|float $amount, PaymentMethod $method, User $user, int $branchId, array $allocations = [], ?string $reference = null, ?string $note = null, ?string $paidAt = null): SupplierPayment
    {
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException(__('Enter an amount greater than zero.'));
        }
        if ($method->isAccount()) {
            throw new BusinessRuleException(__('Choose a real payment method.'));
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $user, $branchId, $allocations, $reference, $note, $paidAt) {
            // Same rules as expenses: cash only, an open shift, and enough cash in the drawer.
            $shift = null;
            if (! empty($allocations['from_drawer'])) {
                if ($method !== PaymentMethod::Cash) {
                    throw new BusinessRuleException(__('Only cash payments can be taken from the drawer.'));
                }
                $shift = $this->shifts->current($user, $branchId);
                if (! $shift) {
                    throw new BusinessRuleException(__('Open a shift to pay from the cash drawer.'));
                }
                if (Money::gt($amount, $this->shifts->expectedCash($shift))) {
                    throw new BusinessRuleException(__('Not enough cash in the drawer.'));
                }
            }
            unset($allocations['from_drawer']);

            $payment = SupplierPayment::withoutGlobalScopes()->create([
                'branch_id' => $branchId, 'supplier_id' => $supplier->id, 'shift_id' => $shift?->id,
                'number' => $this->numbers->next('supplier_payment', $branchId), 'amount' => $amount, 'method' => $method,
                'reference' => $reference, 'paid_at' => $paidAt ?? now()->toDateString(), 'note' => $note, 'user_id' => $user->id,
            ]);

            $remaining = $amount;
            $bills = SupplierBill::withoutGlobalScopes()->where('supplier_id', $supplier->id)->where('status', '!=', 'paid')
                ->when($allocations, fn ($q) => $q->whereIn('id', array_keys($allocations)))
                ->orderBy('bill_date')->orderBy('id')->lockForUpdate()->get();
            foreach ($bills as $bill) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $apply = Money::min($remaining, $allocations[$bill->id] ?? $bill->balance());
                $apply = Money::min($apply, $bill->balance());
                if (! Money::isPositive($apply)) {
                    continue;
                }
                $payment->allocations()->create(['supplier_bill_id' => $bill->id, 'amount' => $apply]);
                $bill->paid = Money::add($bill->paid, $apply);
                $bill->refreshStatus();
                $bill->save();
                $remaining = Money::sub($remaining, $apply);
            }

            $this->ledger->post($supplier, 'payment', $amount, 0, $payment, __('Payment :n (:m)', ['n' => $payment->number, 'm' => $method->label()]), $branchId);
            if ($shift) {
                $this->shifts->cashMovement($shift, $user, 'out', $amount, __('Supplier payment :n', ['n' => $payment->number]), $payment);
            }
            activity('purchases')->causedBy($user)->performedOn($payment)->withProperties(['supplier' => $supplier->name, 'amount' => $amount])->log('Supplier payment');

            return $payment;
        });
    }
}
