<?php

namespace App\Http\Controllers;

use App\Jobs\VerifySubscriptionPayment;
use App\Models\Platform\SubscriptionCallback;
use App\Models\Platform\SubscriptionPayment;
use App\Services\Platform\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FastLipa webhooks for subscription payments (platform account). The body is
 * only a hint: the payment is confirmed against FastLipa's status API on the
 * queue, so a forged webhook can never extend a subscription.
 */
class BillingCallbackController extends Controller
{
    public function __invoke(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $log = SubscriptionCallback::create([
            'gateway' => 'fastlipa',
            'ip' => $request->ip(),
            'payload' => $request->json()->all() ?: $request->all(),
        ]);

        $result = $subscriptions->gateway()->handleCallback($request);
        if (empty($result->raw['signature_valid'])) {
            $log->update(['result' => 'invalid_signature']);

            return response()->json(['ok' => false], 401);
        }

        $payment = $result->reference || $result->providerReference
            ? SubscriptionPayment::query()
                ->where(fn ($q) => $q->when($result->reference, fn ($w) => $w->where('reference', $result->reference))
                    ->when($result->providerReference, fn ($w) => $w->orWhere('provider_reference', $result->providerReference)))
                ->first()
            : null;

        if (! $payment) {
            $log->update(['result' => 'unknown_reference']);

            return response()->json(['ok' => true]); // acknowledge so the provider stops retrying
        }

        $log->update(['subscription_payment_id' => $payment->id, 'result' => $payment->isTerminal() ? 'duplicate' : 'queued']);
        if (! $payment->isTerminal()) {
            VerifySubscriptionPayment::dispatch($payment->id, $result->providerReference);
        }

        return response()->json(['ok' => true]);
    }
}
