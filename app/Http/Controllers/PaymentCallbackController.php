<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\PaymentCallback;
use App\Models\PaymentIntent;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound payment callbacks (api routes: no session / CSRF).
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGateway $driver, PaymentService $payments): JsonResponse
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

        $intent = PaymentIntent::withoutGlobalScopes()
            ->where(fn ($q) => $q->when($result->reference, fn ($w) => $w->where('reference', $result->reference))
                ->when($result->providerReference, fn ($w) => $w->orWhere('provider_reference', $result->providerReference)))
            ->first();
        if (! $intent) {
            $log->update(['result' => 'unknown_reference']);

            return response()->json(['ok' => true]); // acknowledge; nothing to do
        }

        $wasTerminal = $intent->isTerminal();
        $intent = $payments->apply($intent, $result, $result->raw['payload'] ?? []);
        $log->update(['result' => $wasTerminal ? 'duplicate' : $intent->status]);

        return response()->json(['ok' => true]);
    }
}
