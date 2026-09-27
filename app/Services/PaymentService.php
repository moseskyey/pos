<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Support\Integrations\PaymentRequest;
use App\Support\Integrations\PaymentResult;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Mobile-money STK push lifecycle: reserve an intent (committed), call the
 * provider outside any transaction, then move the intent only through allowed
 * transitions based on verified results.
 */
class PaymentService
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function initiate(int $branchId, User $user, PaymentMethod $method, string $phone, string|int|float $amount, string $reference): PaymentIntent
    {
        if (! $method->isMobileMoney()) {
            throw new BusinessRuleException(__('STK push is only for mobile money.'));
        }
        $msisdn = PhoneNumber::normalize($phone);
        if (! $msisdn) {
            throw new BusinessRuleException(__('Enter a valid mobile number.'));
        }
        $amount = Money::round($amount);
        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException(__('Enter an amount greater than zero.'));
        }

        // Phase 1 – reserve (idempotent on reference).
        $intent = DB::transaction(function () use ($branchId, $user, $method, $msisdn, $amount, $reference) {
            $existing = PaymentIntent::withoutGlobalScopes()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            return PaymentIntent::withoutGlobalScopes()->create([
                'branch_id' => $branchId, 'user_id' => $user->id, 'reference' => $reference, 'gateway' => (string) setting('payments.gateway'),
                'method' => $method->value, 'phone' => $msisdn, 'amount' => $amount, 'status' => PaymentIntent::PENDING,
            ]);
        });
        if ($intent->wasRecentlyCreated === false && $intent->provider_reference) {
            return $intent;
        }

        // Phase 2 – provider call, outside any transaction.
        $result = $this->gateway->initiate(new PaymentRequest($reference, $amount, $msisdn, $method->value, __('Payment to :b', ['b' => setting('business.name')])));

        // Phase 3 – record the (non-final) outcome.
        return DB::transaction(function () use ($intent, $result) {
            $intent = PaymentIntent::withoutGlobalScopes()->lockForUpdate()->findOrFail($intent->id);
            $intent->provider_reference ??= $result->providerReference;
            $intent->message = $result->message;
            $intent->payload = ['initiate' => $result->raw];
            $next = $result->status === PaymentResult::FAILED ? PaymentIntent::FAILED : PaymentIntent::PROCESSING;
            if ($intent->canTransitionTo($next)) {
                $intent->status = $next;
            }
            $intent->save();

            return $intent;
        });
    }

    /** Apply a verified result (callback or status query). Idempotent. */
    public function apply(PaymentIntent $intent, PaymentResult $result, array $payload = []): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $result, $payload) {
            $intent = PaymentIntent::withoutGlobalScopes()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->isTerminal()) {
                return $intent;
            }
            $next = match ($result->status) {
                PaymentResult::SUCCESS => PaymentIntent::COMPLETED,
                PaymentResult::FAILED => PaymentIntent::FAILED,
                default => null,
            };
            if ($next === PaymentIntent::COMPLETED && $result->amount !== null && Money::lt($result->amount, $intent->amount)) {
                $next = PaymentIntent::FAILED;
                $result->message = __('Amount paid (:a) is less than requested.', ['a' => money($result->amount)]);
            }
            $intent->provider_reference ??= $result->providerReference;
            $intent->payload = array_merge($intent->payload ?? [], ['last' => $payload ?: $result->raw]);
            if ($result->message) {
                $intent->message = $result->message;
            }
            if ($next && $intent->canTransitionTo($next)) {
                $intent->status = $next;
                $intent->completed_at = $next === PaymentIntent::COMPLETED ? now() : null;
            }
            $intent->save();

            return $intent;
        });
    }

    /** Query the provider for the current status. */
    public function refresh(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->isTerminal() || ! $intent->provider_reference) {
            return $intent;
        }
        PaymentIntent::withoutGlobalScopes()->whereKey($intent->id)->increment('status_checks');
        $result = $this->gateway->status($intent->provider_reference);

        return $this->apply($intent, $result);
    }

    /** Sweep stuck intents (scheduled). */
    public function reconcile(int $olderThanMinutes = 2, int $maxChecks = 30): int
    {
        $count = 0;
        PaymentIntent::withoutGlobalScopes()->whereIn('status', [PaymentIntent::PENDING, PaymentIntent::PROCESSING])
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))->where('status_checks', '<', $maxChecks)
            ->orderBy('id')->limit(100)->get()
            ->each(function (PaymentIntent $intent) use (&$count) {
                if (! $intent->provider_reference && $intent->created_at->lt(now()->subMinutes(30))) {
                    $this->apply($intent, new PaymentResult(PaymentResult::FAILED, message: __('No provider reference – request never reached the provider.')));
                } else {
                    $this->refresh($intent);
                }
                $count++;
            });

        return $count;
    }

    /**
     * Lock and validate a completed intent for use as a sale payment.
     * Must be called inside the checkout transaction.
     */
    public function consume(string $reference, PaymentMethod $method, string $amount, int $branchId): PaymentIntent
    {
        $intent = PaymentIntent::withoutGlobalScopes()->where('reference', $reference)->lockForUpdate()->first();
        if (! $intent || $intent->branch_id !== $branchId || $intent->method !== $method->value) {
            throw new BusinessRuleException(__('Mobile money payment not found.'));
        }
        if ($intent->status !== PaymentIntent::COMPLETED) {
            throw new BusinessRuleException(__('The :m payment has not been confirmed yet.', ['m' => $method->label()]));
        }
        if ($intent->sale_id) {
            throw new BusinessRuleException(__('This mobile money payment was already used.'));
        }
        if (Money::cmp($intent->amount, $amount) !== 0) {
            throw new BusinessRuleException(__('The confirmed amount (:a) does not match.', ['a' => money($intent->amount)]));
        }

        return $intent;
    }
}
