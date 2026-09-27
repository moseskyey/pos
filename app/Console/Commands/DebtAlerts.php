<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresTenant;
use App\Models\Customer;
use App\Notifications\SystemAlert;
use App\Services\AlertService;
use App\Services\CustomerStatementService;
use App\Support\Money;
use Illuminate\Console\Command;

class DebtAlerts extends Command
{
    use RequiresTenant;

    protected $signature = 'dukapos:debt-alerts';

    protected $description = 'Notify managers about customer debts past their due date';

    public function handle(CustomerStatementService $statements, AlertService $alerts): int
    {
        if ($this->missingTenant()) {
            return self::FAILURE;
        }

        $overdue = Customer::query()->where('balance', '>', 0)->get()
            ->map(fn ($c) => ['customer' => $c, 'overdue' => $statements->aging($c)['overdue']])
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
