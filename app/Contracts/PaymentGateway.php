<?php

namespace App\Contracts;

use App\Support\Integrations\PaymentRequest;
use App\Support\Integrations\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Start a payment (e.g. mobile money STK push). */
    public function initiate(PaymentRequest $request): PaymentResult;

    /** Poll the provider for the status of a reference. */
    public function status(string $reference): PaymentResult;

    /** Verify and parse an incoming callback / webhook. */
    public function handleCallback(Request $request): PaymentResult;

    /** Whether this driver supports STK push (vs manual reference entry). */
    public function supportsPush(): bool;
}
