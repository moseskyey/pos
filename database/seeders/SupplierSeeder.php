<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\SupplierLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Carbon::setTestNow(now()->subDays(60));
        $suppliers = [
            ['Bakhresa Group Ltd (Azam)', 'Mohamed Said', '255222861000', 'orders@azam.test', '100-200-300', 30, 0, 'Vingunguti, Dar es Salaam'],
            ['Kilimanjaro Water Co.', 'Anna Mushi', '255754300400', 'sales@kiliwater.test', '100-200-301', 14, 0, 'Moshi, Kilimanjaro'],
            ['Coca-Cola Kwanza Ltd', 'Hassan Juma', '255767500600', 'dsm@cocacola.test', '100-200-302', 7, 0, 'Mikocheni, Dar es Salaam'],
            ['Murzah Wilmar (Korie)', 'Rajesh Patel', '255713700800', 'sales@murzah.test', '100-200-303', 30, 450000, 'Nyerere Road, Dar es Salaam'],
            ['Unilever Tanzania', 'Joyce Mbwambo', '255658900100', 'trade@unilever.test', '100-200-304', 30, 0, 'Chang\'ombe, Dar es Salaam'],
            ['Tanga Fresh Ltd', 'Salim Omari', '255784100200', 'orders@tangafresh.test', '100-200-305', 7, 0, 'Tanga'],
            ['Mansoor Pharma Distributors', 'Dr. Aisha Kassim', '255715300500', 'pharma@mansoor.test', '100-200-306', 30, 0, 'Upanga, Dar es Salaam'],
            ['Kariakoo Hardware Wholesalers', 'Peter Lyimo', '255762700900', null, '100-200-307', 0, 0, 'Kariakoo, Dar es Salaam'],
            ['Simu Direct Electronics', 'Ali Hamad', '255622200300', 'b2b@simudirect.test', '100-200-308', 14, 0, 'Samora Avenue, Dar es Salaam'],
        ];
        $ledger = app(SupplierLedgerService::class);
        foreach ($suppliers as [$name, $contact, $phone, $email, $tin, $terms, $opening, $address]) {
            if (Supplier::where('name', $name)->exists()) {
                continue;
            }
            $supplier = Supplier::create([
                'name' => $name, 'contact_person' => $contact, 'phone' => $phone, 'email' => $email, 'tin' => $tin,
                'payment_terms_days' => $terms, 'opening_balance' => $opening, 'address' => $address, 'is_active' => true,
            ]);
            if ($opening > 0) {
                $ledger->post($supplier, 'opening', 0, $opening, null, 'Opening balance');
            }
        }
        Carbon::setTestNow();
    }
}
