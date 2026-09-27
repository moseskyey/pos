<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Services\CustomerLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Carbon::setTestNow(now()->subDays(40));
        $ledger = app(CustomerLedgerService::class);
        $customers = [
            ['Mama Neema Duka', '255754112233', 'wholesale', 500000, 120000, 'Kariakoo, Mtaa wa Congo'],
            ['Baba Juma Shop', '255713445566', 'wholesale', 300000, 0, 'Mbezi Beach'],
            ['Hoteli ya Amani', '255767889900', 'wholesale', 1000000, 250000, 'Sinza Mori'],
            ['Rehema Mwakalinga', '255715223344', 'retail', 50000, 0, 'Mbezi Luis'],
            ['John Massawe', '255622334455', 'retail', 30000, 15000, 'Kinondoni'],
            ['Fatuma Ali', '255688990011', 'retail', 0, 0, 'Magomeni'],
            ['Emmanuel Kweka', '255744556677', 'retail', 20000, 0, 'Ubungo'],
            ['Zawadi Salon', '255656778899', 'retail', 100000, 0, 'Mwenge'],
            ['Shule ya Msingi Upendo', '255711002200', 'wholesale', 800000, 0, 'Kimara'],
            ['Salma Hamisi', '255699887766', 'retail', 0, 0, 'Tabata'],
            ['Peter Mushi', '255762345678', 'retail', 25000, 0, 'Mikocheni'],
            ['Grace Nyirenda', '255717654321', 'retail', 0, 0, 'Kariakoo'],
        ];
        foreach ($customers as [$name, $phone, $type, $limit, $opening, $address]) {
            if (Customer::where('phone', $phone)->exists()) {
                continue;
            }
            $customer = Customer::create([
                'name' => $name, 'phone' => $phone, 'type' => $type, 'credit_limit' => $limit,
                'opening_balance' => $opening, 'address' => $address.', Dar es Salaam', 'is_active' => true,
            ]);
            $ledger->openingBalance($customer, $opening);
        }
        Carbon::setTestNow();
    }
}
