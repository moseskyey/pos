<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CommissionService;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/** Demo commission rates and this month's sales targets. */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::role('owner')->first();
        if (! $owner) {
            return;
        }
        app(SettingsService::class)->set(['features.commission' => true]);
        $commission = app(CommissionService::class);

        foreach (User::role('cashier')->get() as $i => $cashier) {
            $cashier->update(['commission_rate' => 1.5]);
            if ($branchId = $cashier->default_branch_id ?? $cashier->branches()->value('branches.id')) {
                $commission->saveTargets($branchId, now()->format('Y-m'), [$cashier->id => 3000000 + $i * 500000, 'branch' => 12000000], $owner);
            }
        }
    }
}
