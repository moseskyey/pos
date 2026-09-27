<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\SubscriptionPayment;
use App\Services\Platform\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Re-checks subscription payments stuck in processing, and "failed" ones that
 * FastLipa may still complete, so a missed webhook never loses a payment.
 */
class ReconcileSubscriptionPayments extends Command
{
    protected $signature = 'billing:reconcile';

    protected $description = 'Re-check pending and recently failed subscription payments with FastLipa';

    public function handle(SubscriptionService $subscriptions): int
    {
        $payments = SubscriptionPayment::where('method', 'fastlipa')->whereNotNull('provider_reference')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('status', 'processing')->where('created_at', '<=', now()->subMinute())->where('created_at', '>=', now()->subDays(2)))
                ->orWhere(fn ($w) => $w->where('status', 'failed')->where('recheck_until', '>', now())))
            ->limit(100)->get();

        $completed = 0;
        foreach ($payments as $payment) {
            $completed += $subscriptions->refresh($payment)->status === 'completed' ? 1 : 0;
        }

        // Give up on pushes nobody answered for two days.
        SubscriptionPayment::where('method', 'fastlipa')->where('status', 'processing')->where('created_at', '<', now()->subDays(2))
            ->update(['status' => 'failed', 'message' => __('No confirmation from the payment provider.')]);

        if ($payments->isNotEmpty()) {
            $this->info("Checked {$payments->count()} payment(s), {$completed} completed.");
        }

        return self::SUCCESS;
    }
}
