<?php

namespace App\Gateways\Sms;

use App\Contracts\SmsGateway;
use App\Support\Integrations\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Writes SMS to the log instead of sending (default / testing). */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): SmsResult
    {
        Log::channel(config('logging.default'))->info('[SMS] to '.$to.': '.$message);

        return new SmsResult(true, 'log-'.Str::random(8));
    }
}
