<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Support\PlatformSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sets up a new business: its database, roles, first branch and till, and
 * the owner account, on a free trial.
 */
class TenantProvisioner
{
    public function __construct(protected TenantManager $tenancy, protected TenantDatabase $databases) {}

    /**
     * @param  array{business_name: string, owner_name: string, email: string, phone?: ?string, password: string,
     *               plan_id?: ?int, trial_days?: ?int, branch_name?: ?string, branch_code?: ?string, notes?: ?string}  $data
     */
    public function provision(array $data): Tenant
    {
        $trialDays = (int) ($data['trial_days'] ?? PlatformSettings::get('trial_days', 14));
        $planId = $data['plan_id'] ?? PlatformSettings::get('default_plan_id');

        $tenant = Tenant::create([
            'name' => $data['business_name'],
            'slug' => $this->uniqueSlug($data['business_name']),
            'owner_name' => $data['owner_name'],
            'owner_email' => strtolower($data['email']),
            'owner_phone' => $data['phone'] ?? null,
            'plan_id' => $planId && Plan::whereKey($planId)->exists() ? $planId : null,
            'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays)->endOfDay() : null,
            'notes' => $data['notes'] ?? null,
        ]);

        try {
            $tenant->forceFill([
                'database' => $data['database'] ?? $this->databases->nameFor($tenant),
                'storage_folder' => 'tenants/'.$tenant->id,
            ])->save();

            $this->databases->create($tenant);
            $this->databases->migrate($tenant);

            $this->tenancy->run($tenant, function () use ($data) {
                (new RolesAndPermissionsSeeder)->run();

                DB::transaction(function () use ($data) {
                    $branch = Branch::create([
                        'name' => $data['branch_name'] ?? __('Main branch'),
                        'code' => strtoupper($data['branch_code'] ?? 'MAIN'),
                        'phone' => $data['phone'] ?? null,
                        'is_active' => true,
                    ]);
                    $branch->registers()->create(['name' => __('Till 1'), 'code' => 'T1']);

                    $owner = User::create([
                        'name' => $data['owner_name'],
                        'email' => strtolower($data['email']),
                        'phone' => $data['phone'] ?? null,
                        'password' => $data['password'],
                        'is_active' => true,
                        'default_branch_id' => $branch->id,
                    ]);
                    $owner->assignRole('owner');
                    $owner->branches()->attach($branch);
                });

                setting()->set([
                    'business.name' => $data['business_name'],
                    'business.phone' => $data['phone'] ?? '',
                    'business.email' => strtolower($data['email']),
                ]);
            });

            $tenant->forceFill(['provisioned_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('Provisioning failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            try {
                $this->databases->drop($tenant);
            } catch (Throwable) {
                // leave it for manual clean-up; the error below is what matters
            }
            $tenant->logins()->delete();
            $tenant->forceDelete();

            throw $e;
        }

        return $tenant;
    }

    /** A business with an empty, migrated database (for seeding or importing). */
    public function createEmpty(string $name, array $attributes = []): Tenant
    {
        $tenant = Tenant::create($attributes + ['name' => $name, 'slug' => $this->uniqueSlug($name)]);
        $tenant->forceFill([
            'database' => $attributes['database'] ?? $this->databases->nameFor($tenant),
            'storage_folder' => 'tenants/'.$tenant->id,
        ])->save();
        $this->databases->create($tenant);
        $this->databases->migrate($tenant);
        $tenant->forceFill(['provisioned_at' => now()])->save();

        return $tenant;
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        for ($i = 2; Tenant::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
