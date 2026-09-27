<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    /** Ledger entries (receivable) in a date range with opening/closing balances. */
    public function statement(Customer $customer, Carbon $from, Carbon $to): array
    {
        $opening = CustomerLedgerEntry::query()->where('customer_id', $customer->id)->where('account', 'receivable')
            ->where('created_at', '<', $from->copy()->startOfDay())->orderByDesc('id')->value('balance_after') ?? '0.00';

        $entries = CustomerLedgerEntry::query()->with('reference')->where('customer_id', $customer->id)->where('account', 'receivable')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('id')->get();

        return [
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'opening' => Money::round($opening),
            'entries' => $entries,
            'debits' => Money::sum($entries, 'debit'),
            'credits' => Money::sum($entries, 'credit'),
            'closing' => Money::round($entries->last()?->balance_after ?? $opening),
            'aging' => $this->aging($customer),
        ];
    }

    /** Outstanding balance split into 0–30 / 31–60 / 61–90 / 90+ day buckets. */
    public function aging(Customer $customer): array
    {
        $buckets = ['current' => '0.00', '31_60' => '0.00', '61_90' => '0.00', 'over_90' => '0.00'];
        $invoices = Sale::withoutGlobalScopes()->where('customer_id', $customer->id)->where('status', SaleStatus::Completed->value)
            ->where('balance_due', '>', 0)->get(['created_at', 'balance_due']);
        foreach ($invoices as $invoice) {
            $days = $invoice->created_at->diffInDays(now());
            $key = $days <= 30 ? 'current' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : 'over_90'));
            $buckets[$key] = Money::add($buckets[$key], $invoice->balance_due);
        }
        // Opening balances and other unallocated debt count as oldest.
        $unallocated = Money::sub($customer->balance, Money::sum($invoices, 'balance_due'));
        if (Money::isPositive($unallocated)) {
            $buckets['over_90'] = Money::add($buckets['over_90'], $unallocated);
        }
        $buckets['total'] = Money::add(...array_values($buckets));

        return $buckets;
    }

    /** @return Collection<int, array> debtors with aging buckets */
    public function debtors(): Collection
    {
        return Customer::query()->where('balance', '>', 0)->orderByDesc('balance')->get()
            ->map(fn (Customer $c) => ['customer' => $c] + $this->aging($c));
    }

    public function reminderText(Customer $customer): string
    {
        return __('Habari :name, salio lako kwa :biz ni :amount. Tafadhali lipa mapema. Asante! / Your balance with :biz is :amount.', [
            'name' => explode(' ', $customer->name)[0],
            'biz' => setting('business.name'),
            'amount' => money($customer->balance),
        ]);
    }
}
