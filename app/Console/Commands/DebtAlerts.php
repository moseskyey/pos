<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Notifications\SystemAlert;
use App\Services\AlertService;
use App\Services\CustomerStatementService;
use App\Support\Money;
use Illuminate\Console\Command;

class DebtAlerts extends Command
{
    protected $signature = 'dukapos:debt-alerts';

    protected $description = 'Notify managers about customer debts overdue by more than 30 days';

    public function handle(CustomerStatementService $statements, AlertService $alerts): int
    {
        $overdue = Customer::query()->where('balance', '>', 0)->get()
            ->map(fn ($c) => ['customer' => $c, 'overdue' => Money::add(...array_values(array_intersect_key($statements->aging($c), array_flip(['31_60', '61_90', 'over_90']))))])
            ->filter(fn ($row) => Money::isPositive($row['overdue']));

        if ($overdue->isNotEmpty()) {
            $total = Money::sum($overdue, 'overdue');
            $alerts->notify(null, 'customers.payments', new SystemAlert(
                __(':count customers have overdue debts', ['count' => $overdue->count()]),
                __('Total overdue: :t. Largest: :names', ['t' => money($total), 'names' => $overdue->sortByDesc(fn ($r) => (float) $r['overdue'])->take(3)->map(fn ($r) => $r['customer']->name)->join(', ')]),
                route('reports.show', 'debtors-aging'), 'bi-journal-text', 'danger',
            ));
        }
        $this->info('Overdue customers: '.$overdue->count());

        return self::SUCCESS;
    }
}
