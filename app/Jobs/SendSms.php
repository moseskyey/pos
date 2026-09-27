<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $to, public string $message) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(SmsGateway $sms): void
    {
        $result = $sms->send($this->to, $this->message);
        if (! $result->ok) {
            throw new RuntimeException('SMS failed: '.$result->error);
        }
    }
}
