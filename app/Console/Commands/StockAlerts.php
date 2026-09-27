<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class StockAlerts extends Command
{
    protected $signature = 'dukapos:stock-alerts';

    protected $description = 'Send daily low-stock and expiring batch alerts to managers';

    public function handle(AlertService $alerts): int
    {
        $count = $alerts->dailyStockAlerts();
        $this->info("Sent {$count} alert(s).");

        return self::SUCCESS;
    }
}
