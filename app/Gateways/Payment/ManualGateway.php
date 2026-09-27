<?php

namespace App\Gateways\Payment;

use App\Contracts\PaymentGateway;
use App\Support\Integrations\PaymentRequest;
use App\Support\Integrations\PaymentResult;
use Illuminate\Http\Request;

/**
 * Manual mobile-money: the cashier confirms the customer's payment and types
 * the transaction reference. Always successful at initiate time.
 */
class ManualGateway implements PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(PaymentResult::SUCCESS, $request->reference, $request->meta['manual_reference'] ?? null, $request->amount);
    }

    public function status(string $reference): PaymentResult
    {
        return new PaymentResult(PaymentResult::SUCCESS, $reference);
    }

    public function handleCallback(Request $request): PaymentResult
    {
        return new PaymentResult(PaymentResult::FAILED, message: 'Manual gateway has no callbacks.');
    }

    public function supportsPush(): bool
    {
        return false;
    }
}
