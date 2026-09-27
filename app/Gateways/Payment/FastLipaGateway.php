<?php

namespace App\Gateways\Payment;

use App\Contracts\PaymentGateway;
use App\Support\Integrations\PaymentRequest;
use App\Support\Integrations\PaymentResult;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FastLipa mobile-money STK push driver.
 *
 * The initiation response only means "accepted" – a payment is completed
 * solely by a verified callback or a status query. Unknown statuses are
 * treated as pending, never as success.
 */
class FastLipaGateway implements PaymentGateway
{
    protected const SUCCESS = ['COMPLETED', 'COMPLETE', 'SUCCESS', 'SUCCESSFUL', 'PAID'];

    protected const FAILED = ['FAILED', 'FAIL', 'CANCELLED', 'CANCELED', 'REJECTED', 'EXPIRED', 'DECLINED', 'TIMEOUT', 'INSUFFICIENT_FUNDS'];

    public function supportsPush(): bool
    {
        return (bool) setting('payments.fastlipa_api_key');
    }

    public function initiate(PaymentRequest $request): PaymentResult
    {
        try {
            $response = $this->client()->post(config('services.fastlipa.initiate_path'), [
                'number' => PhoneNumber::normalize($request->phone),
                'amount' => (int) round((float) $request->amount),
                'name' => $request->description ?: setting('business.name'),
                'reference' => $request->reference,
            ]);
        } catch (Throwable $e) {
            // Unknown outcome (e.g. timeout after sending): stay pending, reconciliation resolves it.
            Log::warning('FastLipa initiate error', ['reference' => $request->reference, 'error' => $e->getMessage()]);

            return new PaymentResult(PaymentResult::PENDING, $request->reference, message: __('No response from the payment provider yet. Checking status…'));
        }

        $body = $response->json() ?? [];
        if ($response->failed()) {
            return new PaymentResult($response->serverError() ? PaymentResult::PENDING : PaymentResult::FAILED, $request->reference,
                message: (string) ($body['message'] ?? __('Payment request was rejected.')), raw: $this->safe($body));
        }

        $data = $body['data'] ?? $body;
        $providerRef = $data['tranID'] ?? $data['tranid'] ?? $data['transaction_id'] ?? $data['id'] ?? null;
        $status = $this->map($data['status'] ?? $data['payment_status'] ?? null);

        // Initiation can never complete a payment on its own.
        return new PaymentResult($status === PaymentResult::FAILED ? PaymentResult::FAILED : PaymentResult::PENDING,
            $request->reference, $providerRef ? (string) $providerRef : null, message: $body['message'] ?? null, raw: $this->safe($body));
    }

    public function status(string $reference): PaymentResult
    {
        try {
            $response = $this->client()->get(config('services.fastlipa.status_path'), ['tranid' => $reference, 'tranID' => $reference]);
        } catch (Throwable $e) {
            return new PaymentResult(PaymentResult::PENDING, $reference, message: $e->getMessage());
        }
        if ($response->failed()) {
            return new PaymentResult(PaymentResult::PENDING, $reference, raw: $this->safe($response->json() ?? []));
        }
        $body = $response->json() ?? [];
        $data = $body['data'] ?? $body;

        return new PaymentResult(
            $this->map($data['payment_status'] ?? $data['status'] ?? null),
            $data['reference'] ?? null,
            (string) ($data['tranID'] ?? $data['tranid'] ?? $reference),
            isset($data['amount']) ? (string) $data['amount'] : null,
            $data['message'] ?? null,
            $this->safe($body),
        );
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $payload = $request->json()->all() ?: $request->all();
        $data = $payload['data'] ?? $payload;
        $reference = $data['reference'] ?? $data['external_reference'] ?? null;
        $providerRef = $data['tranID'] ?? $data['tranid'] ?? $data['transaction_id'] ?? null;

        $secret = setting('payments.fastlipa_webhook_secret');
        $signatureValid = false;
        if ($secret) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            $given = (string) $request->header(config('services.fastlipa.signature_header'), '');
            $signatureValid = $given !== '' && hash_equals($expected, strtolower($given));
        }

        $allowed = config('services.fastlipa.allowed_ips', []);
        if ($allowed && ! in_array($request->ip(), $allowed, true)) {
            $signatureValid = false;
        }

        // Without a signing secret, never trust the body: re-query the provider.
        if (! $secret && $providerRef) {
            $verified = $this->status((string) $providerRef);

            return new PaymentResult($verified->status, $reference ?? $verified->reference, (string) $providerRef, $verified->amount, $verified->message,
                ['signature_valid' => true, 'verified_by' => 'status_query', 'payload' => $payload]);
        }

        return new PaymentResult(
            $signatureValid ? $this->map($data['payment_status'] ?? $data['status'] ?? null) : PaymentResult::PENDING,
            $reference, $providerRef ? (string) $providerRef : null,
            isset($data['amount']) ? (string) $data['amount'] : null,
            $data['message'] ?? null,
            ['signature_valid' => $signatureValid, 'verified_by' => 'hmac', 'payload' => $payload],
        );
    }

    protected function map(?string $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match (true) {
            in_array($status, self::SUCCESS, true) => PaymentResult::SUCCESS,
            in_array($status, self::FAILED, true) => PaymentResult::FAILED,
            default => PaymentResult::PENDING,
        };
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) setting('payments.fastlipa_base_url'), '/'))
            ->withToken((string) setting('payments.fastlipa_api_key'))
            ->acceptJson()->asJson()
            ->timeout(config('services.fastlipa.timeout', 20))
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    /** Strip anything that looks like a credential before storing. */
    protected function safe(array $body): array
    {
        return collect($body)->except(['token', 'api_key', 'secret', 'pin'])->all();
    }
}
