<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'DSM01', 'name' => 'Kariakoo', 'address' => 'Msimbazi Street, Kariakoo, Dar es Salaam', 'phone' => '255712000001', 'email' => 'kariakoo@dukapos.test'],
            ['code' => 'DSM02', 'name' => 'Mbezi', 'address' => 'Mbezi Beach, Bagamoyo Road, Dar es Salaam', 'phone' => '255712000002', 'email' => 'mbezi@dukapos.test'],
        ];

        foreach ($branches as $data) {
            $branch = Branch::updateOrCreate(['code' => $data['code']], $data + ['is_active' => true]);
            foreach (['Till 1' => 'T1', 'Till 2' => 'T2'] as $name => $code) {
                $branch->registers()->withoutGlobalScopes()->firstOrCreate(['name' => $name], ['code' => $code, 'is_active' => true]);
            }
        }
    }
}
