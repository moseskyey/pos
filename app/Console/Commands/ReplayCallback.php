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
        if (! tenant()) {
            $this->error('Run it for a business: php artisan tenants:run "payments:replay-callback '.$this->argument('id').'" --tenant=<id>');

            return self::FAILURE;
        }

        $log = PaymentCallback::findOrFail($this->argument('id'));
        $request = Request::create('/api/payments/callback/'.$log->gateway.'/'.tenant()->id, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($log->payload));
        $response = app()->handle($request);
        $this->info('Status: '.$response->getStatusCode().' '.$response->getContent());

        return self::SUCCESS;
    }
}
