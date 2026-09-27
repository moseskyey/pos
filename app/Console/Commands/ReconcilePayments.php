<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'dukapos:reconcile-payments';

    protected $description = 'Query the payment provider for pending mobile-money payments';

    public function handle(PaymentService $payments): int
    {
        $this->info('Checked '.$payments->reconcile().' pending payment(s).');

        return self::SUCCESS;
    }
}
