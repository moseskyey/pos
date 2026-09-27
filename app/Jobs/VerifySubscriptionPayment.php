<?php

namespace App\Jobs;

use App\Models\Platform\SubscriptionPayment;
use App\Services\Platform\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Confirms a subscription payment with FastLipa's status API and applies it. */
class VerifySubscriptionPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(public int $paymentId, public ?string $providerReference = null) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300];
    }

    public function handle(SubscriptionService $subscriptions): void
    {
        $payment = SubscriptionPayment::find($this->paymentId);
        if (! $payment || $payment->isTerminal()) {
            return;
        }
        if (! $payment->provider_reference && $this->providerReference) {
            $payment->update(['provider_reference' => $this->providerReference]);
        }

        $payment = $subscriptions->refresh($payment);
        if ($payment->status === 'processing' && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
        }
    }
}
