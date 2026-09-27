<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $kariakoo = Branch::where('code', 'DSM01')->first();
        $mbezi = Branch::where('code', 'DSM02')->first();

        $users = [
            ['Amina Mushi', 'owner@dukapos.test', '255713100100', 'owner', [$kariakoo, $mbezi], '1234'],
            ['Baraka Mwakyusa', 'manager@dukapos.test', '255713100101', 'manager', [$kariakoo], '4321'],
            ['Neema Kimaro', 'manager.mbezi@dukapos.test', '255713100102', 'manager', [$mbezi], '5678'],
            ['Juma Hassan', 'cashier@dukapos.test', '255713100103', 'cashier', [$kariakoo], '1111'],
            ['Rehema Said', 'cashier.mbezi@dukapos.test', '255713100104', 'cashier', [$mbezi], '2222'],
            ['Daudi Mollel', 'store@dukapos.test', '255713100105', 'storekeeper', [$kariakoo, $mbezi], null],
            ['Grace Lyimo', 'accounts@dukapos.test', '255713100106', 'accountant', [$kariakoo, $mbezi], null],
        ];

        foreach ($users as [$name, $email, $phone, $role, $branches, $pin]) {
            $user = User::withTrashed()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'password' => 'password',
                'is_active' => true,
                'locale' => 'en',
                'default_branch_id' => $branches[0]->id,
            ]);
            $user->syncRoles([$role]);
            $user->branches()->sync(collect($branches)->pluck('id'));
            if ($pin) {
                $user->setPin($pin);
            }
        }
    }
}
