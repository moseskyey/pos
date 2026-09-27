<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach (['Rent' => 'bi-house', 'Electricity (LUKU)' => 'bi-lightning', 'Salaries' => 'bi-people', 'Transport' => 'bi-truck', 'Water' => 'bi-droplet',
            'Internet & airtime' => 'bi-wifi', 'Cleaning & security' => 'bi-shield', 'Repairs' => 'bi-tools', 'Other' => 'bi-three-dots'] as $name => $icon) {
            $categories[$name] = ExpenseCategory::firstOrCreate(['name' => $name], ['icon' => $icon, 'is_active' => true]);
        }

        $owner = User::where('email', 'owner@dukapos.test')->first();
        $service = app(ExpenseService::class);
        $today = now()->startOfDay();

        foreach (Branch::orderBy('id')->get() as $index => $branch) {
            $rent = $index === 0 ? 1500000 : 900000;
            foreach ([
                ['Rent', $rent, 'bank', $today->copy()->startOfMonth()->subMonthNoOverflow(), 'Monthly shop rent', 'Mzee Abdallah (landlord)'],
                ['Rent', $rent, 'bank', $today->copy()->startOfMonth(), 'Monthly shop rent', 'Mzee Abdallah (landlord)'],
                ['Salaries', $index === 0 ? 1800000 : 1200000, 'bank', $today->copy()->startOfMonth()->subMonthNoOverflow()->endOfMonth(), 'Staff salaries', null],
                ['Electricity (LUKU)', 150000, 'mpesa', $today->copy()->subDays(20), 'LUKU tokens', 'TANESCO'],
                ['Electricity (LUKU)', 120000, 'mpesa', $today->copy()->subDays(4), 'LUKU tokens', 'TANESCO'],
                ['Water', 45000, 'mpesa', $today->copy()->subDays(15), 'DAWASA bill', 'DAWASA'],
                ['Internet & airtime', 60000, 'mpesa', $today->copy()->subDays(12), 'Fibre internet', 'TTCL'],
                ['Cleaning & security', 80000, 'cash', $today->copy()->subDays(8), 'Night guard', null],
                ['Transport', 35000, 'cash', $today->copy()->subDays(6), 'Bajaji deliveries', null],
                ['Repairs', 25000, 'cash', $today->copy()->subDays(2), 'Fridge repair', 'Fundi Musa'],
            ] as [$cat, $amount, $method, $date, $desc, $payee]) {
                Carbon::setTestNow($date->copy()->setTime(10, 0));
                $service->record($branch->id, [
                    'expense_category_id' => $categories[$cat]->id, 'expense_date' => $date->toDateString(), 'amount' => $amount,
                    'payment_method' => $method, 'description' => $desc, 'payee' => $payee,
                ], $owner);
            }
            Carbon::setTestNow();
            RecurringExpense::withoutGlobalScopes()->firstOrCreate(['branch_id' => $branch->id, 'description' => 'Monthly shop rent'], [
                'expense_category_id' => $categories['Rent']->id, 'amount' => $rent, 'payment_method' => 'bank', 'frequency' => 'monthly',
                'next_run_date' => $today->copy()->startOfMonth()->addMonthNoOverflow(), 'is_active' => true, 'created_by' => $owner->id,
            ]);
        }
    }
}
