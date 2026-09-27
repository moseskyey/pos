<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\Tenant;
use App\Tenancy\TenantDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant=* : Only these business IDs}
        {--force : Required in production}';

    protected $description = "Run the business migrations on every business's database";

    public function handle(TenantDatabase $databases): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Use --force to migrate in production.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach (TenantsRun::tenants($this->option('tenant')) as $tenant) {
            /** @var Tenant $tenant */
            $this->line("<info>#{$tenant->id}</info> {$tenant->name}");
            $exit = $databases->migrate($tenant);
            $this->output->write(Artisan::output());
            $failed += $exit === 0 ? 0 : 1;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
