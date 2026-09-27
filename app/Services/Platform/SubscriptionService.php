<?php

namespace App\Services\Platform;

use App\Exceptions\BusinessRuleException;
use App\Gateways\Payment\FastLipaGateway;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\Tenant;
use App\Support\Integrations\PaymentRequest;
use App\Support\Integrations\PaymentResult;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\PlatformSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Subscription payments and access periods for businesses.
 *
 * Mobile money follows the same two-phase rule as sales: the payment row is
 * committed first, FastLipa is called outside any transaction, and only a
 * confirmed status (never the initiation response or a webhook body) extends
 * the subscription. Applying a payment is idempotent.
 */
class SubscriptionService
{
    public const MAX_PERIODS = 12;

    public function gateway(): FastLipaGateway
    {
        return FastLipaGateway::forPlatform();
    }

    public function pushAvailable(): bool
    {
        return (bool) PlatformSettings::get('fastlipa_enabled') && $this->gateway()->supportsPush();
    }

    /** @return array{amount: string, months: int} */
    public function quote(Plan $plan, int $periods): array
    {
        $periods = max(1, min(self::MAX_PERIODS, $periods));

        return ['amount' => Money::mul($plan->price, $periods), 'months' => $plan->interval_months * $periods];
    }

    /** Start a mobile-money push to the owner's phone. */
    public function startPush(Tenant $tenant, Plan $plan, int $periods, string $phone, ?int $userId = null): SubscriptionPayment
    {
        if (! $this->pushAvailable()) {
            throw new BusinessRuleException(__('Mobile money payments are not available right now. Please contact support.'));
        }
        if (! $plan->is_active) {
            throw new BusinessRuleException(__('This plan is no longer available.'));
        }
        $msisdn = PhoneNumber::normalize($phone);
        if (! $msisdn) {
            throw new BusinessRuleException(__('Enter a valid mobile number.'));
        }
        ['amount' => $amount, 'months' => $months] = $this->quote($plan, $periods);
        if (! Money::isPositive($amount)) {
            throw new BusinessRuleException(__('This plan is free; no payment is needed.'));
        }

        // A double-click or refresh must not send a second PIN prompt.
        $recent = SubscriptionPayment::where('tenant_id', $tenant->id)->where('method', 'fastlipa')
            ->whereIn('status', ['pending', 'processing'])->where('phone', $msisdn)
            ->where('amount', $amount)->where('created_at', '>=', now()->subMinutes(2))->latest('id')->first();
        if ($recent) {
            return $recent;
        }

        // Phase 1: reserve.
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'reference' => 'SUB'.$tenant->id.'-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'months' => $months,
            'method' => 'fastlipa',
            'status' => 'pending',
            'phone' => $msisdn,
            'initiated_by_user_id' => $userId,
        ]);

        // Phase 2: call the provider outside any transaction.
        $result = $this->gateway()->initiate(new PaymentRequest(
            $payment->reference, $amount, $msisdn, 'mpesa',
            PlatformSettings::get('name', 'DukaPOS').' '.$plan->name,
        ));

        // Phase 3: record what the provider said. Initiation never completes a payment.
        $payment->update([
            'status' => $result->status === PaymentResult::FAILED ? 'failed' : 'processing',
            'provider_reference' => $result->providerReference,
            'message' => $result->message ? Str::limit($result->message, 250) : null,
        ]);

        return $payment;
    }

    /** Ask FastLipa for the real status and apply it. */
    public function refresh(SubscriptionPayment $payment): SubscriptionPayment
    {
        if ($payment->method !== 'fastlipa' || $payment->isTerminal() || ! $payment->provider_reference) {
            return $payment;
        }

        return $this->applyResult($payment, $this->gateway()->status($payment->provider_reference));
    }

    public function applyResult(SubscriptionPayment $payment, PaymentResult $result): SubscriptionPayment
    {
        return DB::connection('central')->transaction(function () use ($payment, $result) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isTerminal() || $result->pending()) {
                return $payment;
            }

            if ($result->successful()) {
                // Never trust a smaller amount than we asked for.
                if ($result->amount !== null && Money::lt($result->amount, $payment->amount)) {
                    $payment->update(['status' => 'failed', 'recheck_until' => null, 'message' => __('Amount paid (:paid) is less than the amount due.', ['paid' => $result->amount])]);

                    return $payment;
                }

                return $this->complete($payment, $result->providerReference);
            }

            // Failed: keep re-checking for a while, FastLipa can report "failed" then "completed".
            $payment->update([
                'status' => 'failed',
                'message' => $result->message ? Str::limit($result->message, 250) : $payment->message,
                'recheck_until' => $payment->recheck_until ?? now()->addMinutes(config('services.fastlipa.recheck_failed_minutes', 30)),
            ]);

            return $payment;
        });
    }

    /** Cash, bank or manual mobile-money payment entered by a platform admin. */
    public function recordManual(Tenant $tenant, Plan $plan, int $periods, string $amount, string $method, ?string $reference, ?string $note, ?PlatformAdmin $admin, ?Carbon $paidAt = null): SubscriptionPayment
    {
        $months = $this->quote($plan, $periods)['months'];

        return DB::connection('central')->transaction(function () use ($tenant, $plan, $months, $amount, $method, $reference, $note, $admin, $paidAt) {
            $payment = SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'reference' => 'MAN'.$tenant->id.'-'.Str::upper(Str::random(10)),
                'provider_reference' => $reference ?: null,
                'amount' => Money::round($amount),
                'months' => $months,
                'method' => $method,
                'status' => 'pending',
                'recorded_by' => $admin?->id,
                'note' => $note,
            ]);

            return $this->complete($payment, $reference, $paidAt);
        });
    }

    /** Give extra days (goodwill, support) without a payment. */
    public function extend(Tenant $tenant, int $days): Tenant
    {
        return DB::connection('central')->transaction(function () use ($tenant, $days) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if ($tenant->status() === Tenant::TRIAL) {
                $tenant->trial_ends_at = $tenant->trial_ends_at->copy()->addDays($days);
            } else {
                $from = $tenant->paid_until && $tenant->paid_until->isFuture() ? $tenant->paid_until : now();
                $tenant->paid_until = $from->copy()->addDays($days)->endOfDay();
            }
            $tenant->save();

            return $tenant;
        });
    }

    /** Refund a payment; optionally take back the period it bought. */
    public function refund(SubscriptionPayment $payment, bool $revokePeriod, ?string $note = null): SubscriptionPayment
    {
        return DB::connection('central')->transaction(function () use ($payment, $revokePeriod, $note) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'completed') {
                throw new BusinessRuleException(__('Only completed payments can be refunded.'));
            }
            if ($revokePeriod && $payment->applied_at) {
                $tenant = Tenant::whereKey($payment->tenant_id)->lockForUpdate()->first();
                if ($tenant?->paid_until) {
                    $tenant->update(['paid_until' => $tenant->paid_until->copy()->subMonthsNoOverflow($payment->months)]);
                }
            }
            $payment->update(['status' => 'refunded', 'note' => trim(($payment->note ? $payment->note."\n" : '').($note ?? ''))]);

            return $payment;
        });
    }

    /** Mark the payment completed and extend the business once. Must run inside a central transaction. */
    protected function complete(SubscriptionPayment $payment, ?string $providerReference, ?Carbon $paidAt = null): SubscriptionPayment
    {
        if ($payment->applied_at) {
            return $payment;
        }

        $tenant = Tenant::withTrashed()->whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
        $start = $tenant->paid_until && $tenant->paid_until->isFuture() ? $tenant->paid_until->copy() : now()->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow($payment->months)->endOfDay();

        $tenant->update(['paid_until' => $end, 'plan_id' => $payment->plan_id ?? $tenant->plan_id, 'last_reminder_at' => null]);
        $payment->update([
            'status' => 'completed',
            'number' => sprintf('INV-SUB-%06d', $payment->id),
            'provider_reference' => $providerReference ?: $payment->provider_reference,
            'paid_at' => $paidAt ?? now(),
            'applied_at' => now(),
            'period_start' => $start,
            'period_end' => $end,
            'recheck_until' => null,
        ]);

        return $payment;
    }
}
