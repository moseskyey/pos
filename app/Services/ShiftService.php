<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashMovement;
use App\Models\Register;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(protected DocumentNumberService $numbers) {}

    public function current(User $user, ?int $branchId = null): ?Shift
    {
        return Shift::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'open')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('opened_at')->first();
    }

    public function open(Register $register, User $user, string|int|float $openingFloat, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($register, $user, $openingFloat, $note) {
            if ($this->current($user)) {
                throw new BusinessRuleException(__('You already have an open shift. Close it first.'));
            }
            $busy = Shift::withoutGlobalScopes()->where('register_id', $register->id)->where('status', 'open')->lockForUpdate()->first();
            if ($busy) {
                throw new BusinessRuleException(__(':register is in use by :user.', ['register' => $register->name, 'user' => $busy->user()->value('name')]));
            }

            $shift = Shift::withoutGlobalScopes()->create([
                'branch_id' => $register->branch_id,
                'register_id' => $register->id,
                'user_id' => $user->id,
                'number' => $this->numbers->next('shift', $register->branch_id),
                'status' => 'open',
                'opened_at' => now(),
                'opening_float' => Money::round($openingFloat),
                'note' => $note,
            ]);
            activity('shifts')->causedBy($user)->performedOn($shift)->withProperties(['float' => $shift->opening_float])->log('Shift opened');

            return $shift;
        });
    }

    public function cashMovement(Shift $shift, User $user, string $type, string|int|float $amount, string $reason, $reference = null): CashMovement
    {
        if (! $shift->isOpen()) {
            throw new BusinessRuleException(__('The shift is closed.'));
        }
        if ($type === 'out' && Money::gt($amount, $this->expectedCash($shift))) {
            throw new BusinessRuleException(__('Not enough cash in the drawer.'));
        }
        $movement = CashMovement::withoutGlobalScopes()->create([
            'branch_id' => $shift->branch_id, 'shift_id' => $shift->id, 'user_id' => $user->id,
            'type' => $type, 'amount' => Money::round($amount), 'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(),
        ]);
        activity('shifts')->causedBy($user)->performedOn($shift)->withProperties(['type' => $type, 'amount' => $movement->amount, 'reason' => $reason])->log('Cash '.$type);

        return $movement;
    }

    /** Full breakdown used by X / Z reports and the close screen. */
    public function summary(Shift $shift): array
    {
        $payments = SalePayment::query()->where('sale_payments.shift_id', $shift->id)
            ->leftJoin('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where(fn ($q) => $q->whereNull('sale_payments.sale_id')->orWhereIn('sales.status', [SaleStatus::Completed->value, SaleStatus::Layaway->value, SaleStatus::Converted->value]))
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as total, COUNT(*) as count')
            ->groupBy('sale_payments.method')->get()
            ->mapWithKeys(fn ($r) => [$r->method instanceof PaymentMethod ? $r->method->value : $r->method => ['total' => Money::round($r->total), 'count' => (int) $r->count]]);

        $sales = DB::table('sales')->where('shift_id', $shift->id)->where('status', SaleStatus::Completed->value)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(subtotal),0) as gross')
            ->first();
        $voids = DB::table('sales')->where('shift_id', $shift->id)->where('status', SaleStatus::Voided->value)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total')->first();

        $returns = ['count' => 0, 'total' => '0.00', 'cash' => '0.00'];
        if (DB::getSchemaBuilder()->hasTable('sale_returns')) {
            $r = DB::table('sale_returns')->where('shift_id', $shift->id)
                ->selectRaw("COUNT(*) as count, COALESCE(SUM(refund_total),0) as total, COALESCE(SUM(CASE WHEN refund_method = 'cash' THEN refund_total ELSE 0 END),0) as cash")->first();
            $returns = ['count' => (int) $r->count, 'total' => Money::round($r->total), 'cash' => Money::round($r->cash)];
        }

        $customerCash = '0.00';
        if (DB::getSchemaBuilder()->hasTable('customer_payments')) {
            $customerCash = Money::round(DB::table('customer_payments')->where('shift_id', $shift->id)->where('method', PaymentMethod::Cash->value)->sum('amount'));
        }
        $expenseCash = '0.00';
        if (DB::getSchemaBuilder()->hasTable('expenses')) {
            $expenseCash = Money::round(DB::table('expenses')->where('shift_id', $shift->id)->where('paid_from_drawer', true)->whereNull('deleted_at')->sum('amount'));
        }

        $cashIn = Money::round(CashMovement::withoutGlobalScopes()->where('shift_id', $shift->id)->where('type', 'in')->sum('amount'));
        $cashOut = Money::round(CashMovement::withoutGlobalScopes()->where('shift_id', $shift->id)->where('type', 'out')->sum('amount'));
        $cashPayments = $payments[PaymentMethod::Cash->value]['total'] ?? '0.00';

        $expected = Money::sub(Money::add($shift->opening_float, $cashPayments, $customerCash, $cashIn), Money::add($cashOut, $returns['cash'], $expenseCash));

        return [
            'sales_count' => (int) $sales->count,
            'gross' => Money::round($sales->gross),
            'discounts' => Money::round($sales->discounts),
            'tax' => Money::round($sales->tax),
            'net_sales' => Money::round($sales->total),
            'voids' => ['count' => (int) $voids->count, 'total' => Money::round($voids->total)],
            'returns' => $returns,
            'payments' => $payments->all(),
            'opening_float' => Money::round($shift->opening_float),
            'cash_payments' => $cashPayments,
            'customer_cash' => $customerCash,
            'expense_cash' => $expenseCash,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expected,
        ];
    }

    public function expectedCash(Shift $shift): string
    {
        return $this->summary($shift)['expected_cash'];
    }

    public function close(Shift $shift, User $user, string|int|float $countedCash, array $denominations = [], ?string $note = null, bool $force = false): Shift
    {
        return DB::transaction(function () use ($shift, $user, $countedCash, $denominations, $note, $force) {
            $shift = Shift::withoutGlobalScopes()->lockForUpdate()->findOrFail($shift->id);
            if (! $shift->isOpen()) {
                throw new BusinessRuleException(__('This shift is already closed.'));
            }
            if (! $force && $shift->user_id !== $user->id && ! $user->can('shifts.manage')) {
                throw new BusinessRuleException(__('Only the cashier or a manager can close this shift.'));
            }
            $summary = $this->summary($shift);
            $overShort = Money::sub($countedCash, $summary['expected_cash']);

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $user->id,
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => Money::round($countedCash),
                'over_short' => $overShort,
                'denominations' => array_filter($denominations, fn ($v) => (int) $v > 0),
                'summary' => $summary,
                'force_closed' => $force || $shift->user_id !== $user->id,
                'note' => trim(($shift->note ? $shift->note."\n" : '').($note ?? '')) ?: null,
            ]);

            activity('shifts')->causedBy($user)->performedOn($shift)
                ->withProperties(['expected' => $summary['expected_cash'], 'counted' => $shift->counted_cash, 'over_short' => $overShort, 'forced' => $shift->force_closed])
                ->log($shift->force_closed ? 'Shift force-closed' : 'Shift closed');

            $threshold = setting('notify.over_short_threshold', 5000);
            if (Money::gt(Money::abs($overShort), $threshold)) {
                app(AlertService::class)->notify($shift->branch_id, 'shifts.manage', new SystemAlert(
                    __('Shift :n is :state by :amount', ['n' => $shift->number, 'state' => Money::isNegative($overShort) ? __('short') : __('over'), 'amount' => money(Money::abs($overShort))]),
                    __(':cashier · :register', ['cashier' => $shift->user->name, 'register' => $shift->register->name]),
                    route('shifts.show', $shift), 'bi-cash-coin', Money::isNegative($overShort) ? 'danger' : 'warning',
                ));
            }

            return $shift;
        });
    }
}
