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
 *   POST /v1/transaction/create   {number, amount, reference, name, webhook_url}
 *        → {status: true, data: {tranID, amount, number, payment_status: "PENDING"}}
 *   GET  /v1/transaction/status?tranid=…
 *        → {status: true, data: {tranid, payment_status, amount, network, time}}
 *   Webhook {event: "payment.completed"|"payment.failed", data: {tranID|tranid, reference,
 *        status|payment_status, amount ("360000" or 360000), number, network}}
 *
 * The initiation response only means "accepted". A webhook body is never
 * trusted on its own: the status endpoint is the source of truth. Unknown
 * statuses are pending, never success.
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
        $body = [
            'number' => PhoneNumber::normalize($request->phone),
            'amount' => (int) round((float) $request->amount),
            'reference' => $request->reference,
            'name' => $request->description ?: setting('business.name'),
        ];
        if (config('services.fastlipa.send_webhook_url')) {
            $body['webhook_url'] = route('payments.callback', 'fastlipa');
        }

        try {
            $response = $this->client()->post(config('services.fastlipa.initiate_path'), $body);
        } catch (Throwable $e) {
            // Unknown outcome (e.g. timeout after sending): stay pending, reconciliation resolves it.
            Log::warning('FastLipa initiate error', ['reference' => $request->reference, 'error' => $e->getMessage()]);

            return new PaymentResult(PaymentResult::PENDING, $request->reference, message: __('No response from the payment provider yet. Checking status…'));
        }

        $json = $response->json() ?? [];
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $providerRef = $this->tranId($data);

        if ($response->serverError()) {
            return new PaymentResult(PaymentResult::PENDING, $request->reference, $providerRef, message: $this->message($json), raw: $this->safe($json));
        }
        // 4xx, or HTTP 200 with {"status": false}: the request was refused and nothing was sent to the phone.
        if ($response->failed() || ($json['status'] ?? null) === false) {
            return new PaymentResult(PaymentResult::FAILED, $request->reference, $providerRef,
                message: $this->message($json) ?? __('Payment request was rejected.'), raw: $this->safe($json));
        }

        // Initiation can never complete a payment on its own.
        $status = $this->map($data['payment_status'] ?? null);

        return new PaymentResult($status === PaymentResult::FAILED ? PaymentResult::FAILED : PaymentResult::PENDING,
            $request->reference, $providerRef, message: $this->message($json), raw: $this->safe($json));
    }

    public function status(string $reference): PaymentResult
    {
        try {
            $response = $this->client(retry: true)->get(config('services.fastlipa.status_path'), ['tranid' => $reference]);
        } catch (Throwable $e) {
            return new PaymentResult(PaymentResult::PENDING, providerReference: $reference, message: $e->getMessage());
        }
        $json = $response->json() ?? [];
        if ($response->failed() || ($json['status'] ?? null) === false || ! is_array($json['data'] ?? null)) {
            // Could not confirm either way: stay pending.
            return new PaymentResult(PaymentResult::PENDING, providerReference: $reference, message: $this->message($json), raw: $this->safe($json));
        }
        $data = $json['data'];

        return new PaymentResult(
            $this->map($data['payment_status'] ?? $data['status'] ?? null),
            isset($data['reference']) ? (string) $data['reference'] : null,
            $this->tranId($data) ?? $reference,
            isset($data['amount']) ? (string) $data['amount'] : null,
            $this->message($json),
            ['verified_by' => 'status_query'] + $this->safe($json),
        );
    }

    /**
     * Parse and authenticate a webhook. The returned status is only a hint;
     * PaymentService re-queries the status endpoint before changing anything.
     */
    public function handleCallback(Request $request): PaymentResult
    {
        $payload = $request->json()->all() ?: $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $signatureValid = true;
        $secret = setting('payments.fastlipa_webhook_secret');
        $given = (string) $request->header(config('services.fastlipa.signature_header'), '');
        if ($secret && $given !== '') {
            // A signature that is present but wrong means the body was forged or altered.
            $signatureValid = hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($given));
        }
        $allowed = config('services.fastlipa.allowed_ips', []);
        if ($allowed && ! in_array($request->ip(), $allowed, true)) {
            $signatureValid = false;
        }

        $status = $data['payment_status'] ?? $data['status'] ?? null;
        if (! $status && is_string($payload['event'] ?? null)) {
            $status = str_replace('payment.', '', $payload['event']);
        }

        return new PaymentResult(
            $this->map($status),
            isset($data['reference']) ? (string) $data['reference'] : null,
            $this->tranId($data),
            isset($data['amount']) ? (string) $data['amount'] : null,
            null,
            ['signature_valid' => $signatureValid, 'event' => $payload['event'] ?? null, 'payload' => $payload],
        );
    }

    protected function map(mixed $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match (true) {
            in_array($status, self::SUCCESS, true) => PaymentResult::SUCCESS,
            in_array($status, self::FAILED, true) => PaymentResult::FAILED,
            default => PaymentResult::PENDING,
        };
    }

    /** FastLipa uses both "tranID" and "tranid". */
    protected function tranId(array $data): ?string
    {
        $id = $data['tranID'] ?? $data['tranid'] ?? $data['tranId'] ?? $data['transaction_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    protected function message(array $json): ?string
    {
        return isset($json['message']) && is_string($json['message']) ? $json['message'] : null;
    }

    /**
     * Status queries are safe to retry. Initiation is not: a retry after a
     * connection drop could send the customer a second PIN prompt.
     */
    protected function client(bool $retry = false): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) setting('payments.fastlipa_base_url', 'https://api.fastlipa.com'), '/'))
            ->withToken((string) setting('payments.fastlipa_api_key'))
            ->acceptJson()->asJson()
            ->timeout(config('services.fastlipa.timeout', 20));

        return $retry ? $client->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false) : $client;
    }

    /** Strip anything that looks like a credential before storing. */
    protected function safe(array $body): array
    {
        return collect($body)->except(['token', 'api_key', 'secret', 'pin'])->all();
    }
}
