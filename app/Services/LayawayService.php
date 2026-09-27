<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Layaway / deposits: stock is reserved (issued) when the layaway is created;
 * the sale completes once fully paid, or is cancelled with deposits refunded
 * to store credit.
 */
class LayawayService
{
    public function __construct(protected StockService $stock, protected ShiftService $shifts, protected CustomerLedgerService $ledger, protected LoyaltyService $loyalty) {}

    public function addPayment(Sale $sale, string|int|float $amount, PaymentMethod $method, User $user, ?string $reference = null): Sale
    {
        return DB::transaction(function () use ($sale, $amount, $method, $user, $reference) {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== SaleStatus::Layaway) {
                throw new BusinessRuleException(__('This is not an open layaway.'));
            }
            $amount = Money::min(Money::round($amount), $sale->balance_due);
            if (! Money::isPositive($amount)) {
                throw new BusinessRuleException(__('Enter an amount greater than zero.'));
            }
            if ($method->isMobileMoney() && ! $reference) {
                throw new BusinessRuleException(__('Enter the mobile money transaction reference.'));
            }
            if ($method->isAccount()) {
                throw new BusinessRuleException(__('Choose a real payment method.'));
            }
            $shift = $this->shifts->current($user, $sale->branch_id);
            if ($method === PaymentMethod::Cash && ! $shift) {
                throw new BusinessRuleException(__('Open a shift to receive cash.'));
            }

            $sale->payments()->create([
                'branch_id' => $sale->branch_id, 'shift_id' => $shift?->id, 'method' => $method->value,
                'amount' => $amount, 'reference' => $reference, 'received_by' => $user->id,
            ]);
            $sale->paid_total = Money::add($sale->paid_total, $amount);
            $sale->balance_due = Money::sub($sale->total, $sale->paid_total);
            if (! Money::isPositive($sale->balance_due)) {
                $sale->status = SaleStatus::Completed;
                $sale->completed_at = now();
                if ($sale->customer_id && $this->loyalty->enabled()) {
                    $sale->loyalty_earned = $this->loyalty->earn(Customer::find($sale->customer_id), $sale, $sale->total);
                }
            }
            $sale->save();
            activity('sales')->causedBy($user)->performedOn($sale)->withProperties(['amount' => $amount])->log('Layaway payment received');

            return $sale;
        });
    }

    public function cancel(Sale $sale, User $user, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== SaleStatus::Layaway) {
                throw new BusinessRuleException(__('This is not an open layaway.'));
            }
            $movements = StockMovement::withoutGlobalScopes()->with('product')
                ->where('reference_type', $sale->getMorphClass())->where('reference_id', $sale->id)->where('type', MovementType::Sale->value)->get();
            foreach ($movements as $m) {
                $this->stock->receive($sale->branch_id, $m->product, Qty::abs($m->quantity), MovementType::Void, $sale, $m->unit_cost, null, null, __('Layaway cancelled'), $m->batch_id);
            }
            if (Money::isPositive($sale->paid_total) && $sale->customer_id) {
                $this->ledger->post(Customer::find($sale->customer_id), 'layaway_refund', 0, $sale->paid_total, $sale, __('Deposit from :n', ['n' => $sale->number]), 'store_credit');
            }
            $sale->update(['status' => SaleStatus::Voided, 'voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason]);
            activity('sales')->causedBy($user)->performedOn($sale)->withProperties(['reason' => $reason])->log('Layaway cancelled');

            return $sale;
        });
    }
}
