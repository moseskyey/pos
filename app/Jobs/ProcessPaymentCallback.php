<?php

namespace App\Jobs;

use App\Models\PaymentCallback;
use App\Models\PaymentIntent;
use App\Services\PaymentService;
use App\Support\Integrations\PaymentResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Verifies a payment webhook against the provider's status endpoint and
 * applies the result. The webhook body itself never changes an intent.
 */
class ProcessPaymentCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(
        public int $callbackId,
        public int $intentId,
        public ?string $providerReference,
        public string $hint,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300];
    }

    public function handle(PaymentService $payments): void
    {
        $log = PaymentCallback::find($this->callbackId);
        $intent = PaymentIntent::withoutGlobalScopes()->find($this->intentId);
        if (! $intent) {
            return;
        }
        if ($intent->isTerminal()) {
            $log?->update(['result' => 'duplicate']);

            return;
        }

        $intent = $payments->refresh($intent, $this->providerReference);
        $log?->update(['result' => $intent->status]);

        // The webhook says paid but the status endpoint has not caught up yet: look again shortly.
        if ($this->hint === PaymentResult::SUCCESS && $intent->status !== PaymentIntent::COMPLETED && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
        }
    }
}
