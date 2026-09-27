<?php

namespace App\Console\Commands;

use App\Services\ExpenseService;
use Illuminate\Console\Command;

class RunRecurringExpenses extends Command
{
    protected $signature = 'dukapos:recurring-expenses';

    protected $description = 'Create expenses from due recurring templates (rent, salaries…)';

    public function handle(ExpenseService $expenses): int
    {
        $this->info('Created '.$expenses->runRecurring().' expense(s).');

        return self::SUCCESS;
    }
}
