<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use App\Support\Money;

class LoyaltyService
{
    public function enabled(): bool
    {
        return (bool) setting('loyalty.enabled', false);
    }

    public function pointsFor(string $amount): int
    {
        $per = (int) setting('loyalty.earn_per_amount', 1000);

        return $per > 0 ? (int) floor((float) Money::div($amount, $per, 4)) : 0;
    }

    public function valueOf(int $points): string
    {
        return Money::mul($points, setting('loyalty.point_value', 10));
    }

    public function earn(Customer $customer, Sale $sale, string $eligibleAmount): int
    {
        $points = $this->pointsFor($eligibleAmount);

        return $points > 0 ? $this->record($customer, $sale, 'earn', $points, __('Earned on :n', ['n' => $sale->number])) : 0;
    }

    public function redeem(Customer $customer, Sale $sale, int $points): int
    {
        return $points > 0 ? $this->record($customer, $sale, 'redeem', -$points, __('Redeemed on :n', ['n' => $sale->number])) : 0;
    }

    /** Reverse all points of a sale (void / return). */
    public function reverse(Sale $sale, ?int $earnedPoints = null): void
    {
        if (! $sale->customer_id) {
            return;
        }
        $customer = Customer::withTrashed()->lockForUpdate()->find($sale->customer_id);
        $net = -1 * ($earnedPoints ?? $sale->loyalty_earned) + ($earnedPoints === null ? $sale->loyalty_redeemed : 0);
        if ($net !== 0) {
            $this->record($customer, $sale, 'reverse', $net, __('Reversed for :n', ['n' => $sale->number]));
        }
    }

    protected function record(Customer $customer, Sale $sale, string $type, int $points, string $note): int
    {
        $customer->loyalty_points = max(0, $customer->loyalty_points + $points);
        $customer->save();
        LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'sale_id' => $sale->id, 'type' => $type,
            'points' => $points, 'balance_after' => $customer->loyalty_points, 'note' => $note,
        ]);

        return abs($points);
    }
}
