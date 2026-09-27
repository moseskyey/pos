<?php

namespace App\Console\Commands;

use App\Models\PaymentCallback;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ReplayCallback extends Command
{
    protected $signature = 'payments:replay-callback {id : payment_callbacks.id}';

    protected $description = 'Replay a logged payment callback (verification still applies)';

    public function handle(): int
    {
        $log = PaymentCallback::findOrFail($this->argument('id'));
        $request = Request::create('/api/payments/callback/'.$log->gateway, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($log->payload));
        $response = app()->handle($request);
        $this->info('Status: '.$response->getStatusCode().' '.$response->getContent());

        return self::SUCCESS;
    }
}
