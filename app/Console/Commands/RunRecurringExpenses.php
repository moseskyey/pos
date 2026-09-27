<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresTenant;
use App\Services\ExpenseService;
use Illuminate\Console\Command;

class RunRecurringExpenses extends Command
{
    use RequiresTenant;

    protected $signature = 'dukapos:recurring-expenses';

    protected $description = 'Create expenses from due recurring templates (rent, salaries…)';

    public function handle(ExpenseService $expenses): int
    {
        if ($this->missingTenant()) {
            return self::FAILURE;
        }

        $this->info('Created '.$expenses->runRecurring().' expense(s).');

        return self::SUCCESS;
    }
}
