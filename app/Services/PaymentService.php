<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Notifications\SystemAlert;
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

    /**
     * Apply a verified provider result (status query). Idempotent.
     *
     * A provider "failed" is not trusted as final: FastLipa has been seen to
     * report failed and then completed for the same transaction a few minutes
     * later. Such intents stay re-checkable until recheck_until; a completion
     * that arrives in that window is accepted and flagged as late.
     */
    public function apply(PaymentIntent $intent, PaymentResult $result, array $payload = []): PaymentIntent
    {
        $late = false;
        $intent = DB::transaction(function () use ($intent, $result, $payload, &$late) {
            $intent = PaymentIntent::withoutGlobalScopes()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->isTerminal()) {
                return $intent;
            }
            $next = match ($result->status) {
                PaymentResult::SUCCESS => PaymentIntent::COMPLETED,
                PaymentResult::FAILED => PaymentIntent::FAILED,
                default => null,
            };
            $underpaid = $next === PaymentIntent::COMPLETED && $result->amount !== null && is_numeric($result->amount)
                && Money::lt($result->amount, $intent->amount);
            if ($underpaid) {
                $next = PaymentIntent::FAILED;
                $result->message = __('Amount paid (:a) is less than requested.', ['a' => money($result->amount)]);
            }

            $intent->provider_reference ??= $result->providerReference;
            $intent->payload = array_merge($intent->payload ?? [], ['last' => $payload ?: $result->raw]);
            if ($result->message) {
                $intent->message = $result->message;
            }

            if ($next === PaymentIntent::COMPLETED && $intent->canTransitionTo($next)) {
                $late = $intent->status === PaymentIntent::FAILED;
                $intent->status = PaymentIntent::COMPLETED;
                $intent->completed_at = now();
                $intent->recheck_until = null;
                $intent->late_completed_at = $late ? now() : null;
                if ($late) {
                    $intent->message = __('Confirmed after it was reported failed.');
                }
            } elseif ($next === PaymentIntent::FAILED && $intent->status !== PaymentIntent::FAILED && $intent->canTransitionTo($next)) {
                $intent->status = PaymentIntent::FAILED;
                // Underpayment is our decision and final; a provider failure may still flip.
                $intent->recheck_until = $underpaid || ! $intent->provider_reference
                    ? null : now()->addMinutes(config('services.fastlipa.recheck_failed_minutes', 30));
            }
            $intent->save();

            return $intent;
        });

        if ($late) {
            $this->alertLateCompletion($intent);
        }

        return $intent;
    }

    /** Query the provider for the current status. */
    public function refresh(PaymentIntent $intent, ?string $providerReference = null): PaymentIntent
    {
        if ($intent->isTerminal()) {
            return $intent;
        }
        if (! $intent->provider_reference && $providerReference) {
            PaymentIntent::withoutGlobalScopes()->whereKey($intent->id)->whereNull('provider_reference')->update(['provider_reference' => $providerReference]);
            $intent->provider_reference = $providerReference;
        }
        if (! $intent->provider_reference) {
            return $intent;
        }
        PaymentIntent::withoutGlobalScopes()->whereKey($intent->id)->increment('status_checks');
        $result = $this->gateway->status($intent->provider_reference);

        return $this->apply($intent, $result);
    }

    /** Sweep stuck and recently failed intents (scheduled every minute). */
    public function reconcile(int $olderThanMinutes = 2, int $maxChecks = 30): int
    {
        $count = 0;
        PaymentIntent::withoutGlobalScopes()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('status', [PaymentIntent::PENDING, PaymentIntent::PROCESSING])
                    ->where('created_at', '<=', now()->subMinutes($olderThanMinutes)))
                ->orWhere(fn ($w) => $w->where('status', PaymentIntent::FAILED)->where('recheck_until', '>', now())
                    ->whereNotNull('provider_reference')))
            ->where('status_checks', '<', $maxChecks)
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
     * Money arrived after the till was told the payment failed. If no sale
     * used it, a manager must either attach it or refund the customer.
     */
    protected function alertLateCompletion(PaymentIntent $intent): void
    {
        if ($intent->sale_id) {
            return;
        }
        $method = PaymentMethod::from($intent->method)->label();
        $alert = new SystemAlert(
            __('Late :m payment received', ['m' => $method]),
            __(':amount from :phone (ref :ref) was confirmed after it was reported failed and is not attached to a sale. Check with the customer: complete their sale or refund them.', [
                'amount' => money($intent->amount), 'phone' => PhoneNumber::display($intent->phone), 'ref' => $intent->provider_reference ?? $intent->reference,
            ]),
            null, 'bi-phone-vibrate', 'warning', true,
        );
        $cashier = User::find($intent->user_id);
        app(AlertService::class)->notify($intent->branch_id, 'sales.void', $alert, $cashier);
        $cashier?->notify($alert);
        activity('payments')->performedOn($intent)->withProperties(['reference' => $intent->reference, 'provider_reference' => $intent->provider_reference, 'amount' => $intent->amount])
            ->log('Late mobile money payment');
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
