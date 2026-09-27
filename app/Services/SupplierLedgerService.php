<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierLedgerEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounts payable. Credit increases what we owe the supplier (bills),
 * debit decreases it (payments, returns).
 */
class SupplierLedgerService
{
    public function post(Supplier $supplier, string $type, string|int|float $debit = 0, string|int|float $credit = 0, ?Model $reference = null, ?string $note = null, ?int $branchId = null): SupplierLedgerEntry
    {
        $callback = function () use ($supplier, $type, $debit, $credit, $reference, $note, $branchId) {
            $locked = Supplier::withTrashed()->lockForUpdate()->findOrFail($supplier->id);
            $locked->balance = Money::add($locked->balance, Money::sub($credit, $debit));
            $locked->save();
            $supplier->setRawAttributes($locked->getAttributes(), true);

            return SupplierLedgerEntry::create([
                'supplier_id' => $supplier->id,
                'branch_id' => $branchId ?? $reference?->branch_id,
                'type' => $type,
                'debit' => Money::round($debit),
                'credit' => Money::round($credit),
                'balance_after' => $locked->balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => Auth::id(),
            ]);
        };

        return DB::transactionLevel() > 0 ? $callback() : DB::transaction($callback);
    }

    /** Ledger entries in a date range with opening/closing balances (what we owe). */
    public function statement(Supplier $supplier, Carbon $from, Carbon $to): array
    {
        $opening = SupplierLedgerEntry::query()->where('supplier_id', $supplier->id)
            ->where('created_at', '<', $from->copy()->startOfDay())->orderByDesc('id')->value('balance_after') ?? '0.00';
        $entries = SupplierLedgerEntry::query()->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('id')->get();

        return [
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'opening' => Money::round($opening),
            'entries' => $entries,
            'billed' => Money::sum($entries, 'credit'),
            'paid' => Money::sum($entries, 'debit'),
            'closing' => Money::round($entries->last()?->balance_after ?? $opening),
            'aging' => $this->aging($supplier),
        ];
    }

    public function aging(Supplier $supplier): array
    {
        $buckets = ['current' => '0.00', '31_60' => '0.00', '61_90' => '0.00', 'over_90' => '0.00'];
        $bills = SupplierBill::withoutGlobalScopes()->where('supplier_id', $supplier->id)->where('status', '!=', 'paid')->get();
        foreach ($bills as $bill) {
            $days = $bill->bill_date->diffInDays(now());
            $key = $days <= 30 ? 'current' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90'));
            $buckets[$key] = Money::add($buckets[$key], $bill->balance());
        }
        $unallocated = Money::sub($supplier->balance, Money::sum($bills, fn ($b) => $b->balance()));
        if (Money::isPositive($unallocated)) {
            $buckets['over_90'] = Money::add($buckets['over_90'], $unallocated);
        }
        $buckets['total'] = Money::add(...array_values($buckets));

        return $buckets;
    }
}
