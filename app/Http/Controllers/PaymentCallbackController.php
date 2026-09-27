<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Jobs\ProcessPaymentCallback;
use App\Models\PaymentCallback;
use App\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound payment callbacks (api routes: no session / CSRF).
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGateway $driver): JsonResponse
    {
        $log = PaymentCallback::create([
            'gateway' => $gateway,
            'ip' => $request->ip(),
            'payload' => $request->json()->all() ?: $request->all(),
        ]);

        if ($gateway !== setting('payments.gateway')) {
            $log->update(['result' => 'gateway_mismatch']);

            return response()->json(['ok' => false], 404);
        }

        $result = $driver->handleCallback($request);
        $log->update(['reference' => $result->reference, 'signature_valid' => (bool) ($result->raw['signature_valid'] ?? false)]);
        if (empty($result->raw['signature_valid'])) {
            $log->update(['result' => 'invalid_signature']);

            return response()->json(['ok' => false], 401);
        }

        $intent = $result->reference || $result->providerReference
            ? PaymentIntent::withoutGlobalScopes()
                ->where(fn ($q) => $q->when($result->reference, fn ($w) => $w->where('reference', $result->reference))
                    ->when($result->providerReference, fn ($w) => $w->orWhere('provider_reference', $result->providerReference)))
                ->first()
            : null;
        if (! $intent) {
            $log->update(['result' => 'unknown_reference']);

            return response()->json(['ok' => true]); // acknowledge so the provider stops retrying
        }
        if ($intent->isTerminal()) {
            $log->update(['result' => 'duplicate']);

            return response()->json(['ok' => true]);
        }

        // Acknowledge fast; verification against the status endpoint happens on the queue.
        $log->update(['result' => 'queued']);
        ProcessPaymentCallback::dispatch($log->id, $intent->id, $result->providerReference, $result->status);

        return response()->json(['ok' => true]);
    }
}
