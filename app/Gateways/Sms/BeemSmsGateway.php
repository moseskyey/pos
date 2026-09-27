<?php

namespace App\Gateways\Sms;

use App\Contracts\SmsGateway;
use App\Support\Integrations\SmsResult;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Beem Africa SMS (https://beem.africa). Credentials are stored encrypted in Settings.
 */
class BeemSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        $msisdn = PhoneNumber::normalize($to);
        if (! $msisdn) {
            return new SmsResult(false, error: __('Invalid phone number.'));
        }
        $key = setting('sms.api_key');
        $secret = setting('sms.api_secret');
        if (! $key || ! $secret) {
            return new SmsResult(false, error: __('SMS gateway is not configured.'));
        }

        try {
            $response = Http::withBasicAuth($key, $secret)->acceptJson()->asJson()
                ->timeout(config('services.beem.timeout', 15))
                ->post(config('services.beem.url'), [
                    'source_addr' => setting('sms.sender_id', 'INFO'),
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [['recipient_id' => 1, 'dest_addr' => $msisdn]],
                ]);
        } catch (Throwable $e) {
            Log::warning('Beem SMS error: '.$e->getMessage());

            return new SmsResult(false, error: $e->getMessage());
        }

        $body = $response->json() ?? [];
        if ($response->successful() && ($body['successful'] ?? false)) {
            return new SmsResult(true, (string) ($body['request_id'] ?? ''));
        }

        return new SmsResult(false, error: (string) ($body['message'] ?? 'HTTP '.$response->status()));
    }
}
