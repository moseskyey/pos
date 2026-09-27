<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresTenant;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    use RequiresTenant;

    protected $signature = 'dukapos:reconcile-payments';

    protected $description = 'Query the payment provider for pending mobile-money payments';

    public function handle(PaymentService $payments): int
    {
        if ($this->missingTenant()) {
            return self::FAILURE;
        }

        $this->info('Checked '.$payments->reconcile().' pending payment(s).');

        return self::SUCCESS;
    }
}
