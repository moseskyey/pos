<?php

namespace Database\Seeders;

use App\Models\Platform\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantProvisioner;
use Illuminate\Database\Seeder;

/**
 * Central (platform) data: plans. Outside production also a platform admin
 * and a demo business with its own database full of demo data.
 *
 * Businesses are created by signing up, by an admin, or with tenants:create.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Inside a business (tests, tenants:run) this seeds that business only.
        if (tenant()) {
            $this->call(TenantDatabaseSeeder::class);

            return;
        }

        $this->call(PlatformSeeder::class);

        if (! (app()->environment(['local', 'testing', 'demo']) || config('app.seed_demo'))) {
            return;
        }

        PlatformAdmin::firstOrCreate(['email' => 'admin@dukapos.test'], [
            'name' => 'Platform Admin', 'password' => 'password', 'is_super' => true, 'is_active' => true,
        ]);
        PlatformSettings::set(['support_phone' => '+255 712 000 000', 'support_email' => 'support@dukapos.test']);

        if (! Tenant::where('slug', 'dukapos-demo-store')->exists()) {
            $provisioner = app(TenantProvisioner::class);
            $tenant = $provisioner->createEmpty('DukaPOS Demo Store', [
                'slug' => 'dukapos-demo-store',
                'plan_id' => Plan::where('slug', 'business')->value('id'),
                'paid_until' => now()->addYear()->endOfDay(),
            ]);
            app(TenantManager::class)->run($tenant, function () use ($tenant) {
                $this->call(TenantDatabaseSeeder::class);
                $owner = User::role('owner')->orderBy('id')->first();
                $tenant->update(['owner_name' => $owner?->name, 'owner_email' => $owner?->email, 'owner_phone' => $owner?->phone]);
            });
            $this->command?->info("Demo business #{$tenant->id} created (database {$tenant->database}).");
        }
    }
}
