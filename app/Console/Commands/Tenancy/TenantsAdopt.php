<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use App\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path for an install from before multi-tenancy: the existing data
 * becomes the first business, in place. Nothing is moved or copied.
 */
class TenantsAdopt extends Command
{
    protected $signature = 'tenants:adopt
        {--name= : Business name (default: the business name in Settings)}
        {--paid-until= : Paid up to this date (YYYY-MM-DD); otherwise a normal trial starts}
        {--auto : For deploy scripts: adopt only when there is old data to adopt, otherwise do nothing}';

    protected $description = 'Turn the existing single-business database into the first business';

    public function handle(TenantManager $tenancy): int
    {
        if (! Schema::connection('central')->hasTable('users')) {
            if ($this->option('auto')) {
                return self::SUCCESS; // a multi-business install: nothing to adopt
            }
            $this->error('No existing business data found in the central database. Use tenants:create for new businesses.');

            return self::FAILURE;
        }
        if ($existing = Tenant::where('database', config('database.connections.central.database'))->first()) {
            $this->warn("Already adopted as business #{$existing->id} ({$existing->name}). Re-indexing its users.");
            $tenancy->run($existing, fn () => User::withTrashed()->each(fn (User $u) => $u->syncTenantLogin()));

            return self::SUCCESS;
        }

        $tenant = Tenant::create([
            'name' => 'Business',
            'slug' => 'business-'.now()->format('YmdHis'),
            'database' => config('database.connections.central.database'),
            'storage_folder' => null, // keep files, backups and links where they already are
            'trial_ends_at' => now()->addDays((int) PlatformSettings::get('trial_days', 14))->endOfDay(),
            'paid_until' => $this->option('paid-until') ? now()->parse($this->option('paid-until'))->endOfDay() : null,
            'provisioned_at' => now(),
        ]);

        $tenancy->run($tenant, function (Tenant $tenant) {
            $owner = User::role('owner')->orderBy('id')->first();
            $name = $this->option('name') ?: setting('business.name') ?: 'Business';
            $tenant->update([
                'name' => $name,
                'slug' => app(TenantProvisioner::class)->uniqueSlug($name),
                'owner_name' => $owner?->name,
                'owner_email' => $owner?->email,
                'owner_phone' => $owner?->phone,
            ]);
            User::withTrashed()->each(fn (User $u) => $u->syncTenantLogin());
        });

        PlatformSettings::set(['legacy_tenant_id' => $tenant->id]);
        $this->info("Adopted as business #{$tenant->id} ({$tenant->name}). Its users sign in exactly as before.");

        return self::SUCCESS;
    }
}
