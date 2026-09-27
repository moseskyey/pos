<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresTenant;
use App\Services\AlertService;
use Illuminate\Console\Command;

class StockAlerts extends Command
{
    use RequiresTenant;

    protected $signature = 'dukapos:stock-alerts';

    protected $description = 'Send daily low-stock and expiring batch alerts to managers';

    public function handle(AlertService $alerts): int
    {
        if ($this->missingTenant()) {
            return self::FAILURE;
        }

        $count = $alerts->dailyStockAlerts();
        $this->info("Sent {$count} alert(s).");

        return self::SUCCESS;
    }
}
