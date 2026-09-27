<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Platform\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Runs an artisan command once per business, e.g.
 *   php artisan tenants:run dukapos:stock-alerts
 *   php artisan tenants:run "backup:run --only-db" --tenant=4
 */
class TenantsRun extends Command
{
    protected $signature = 'tenants:run
        {commandline : The artisan command (quote it when it has options)}
        {--tenant=* : Only these business IDs}
        {--active : Skip businesses whose subscription has ended or is suspended}';

    protected $description = 'Run an artisan command for each business';

    public function handle(TenantManager $tenancy): int
    {
        $failed = 0;
        foreach (static::tenants($this->option('tenant')) as $tenant) {
            if ($this->option('active') && ! $tenant->canUseApp()) {
                continue;
            }
            try {
                $exit = $tenancy->run($tenant, fn () => Artisan::call($this->argument('commandline')));
                $output = trim(Artisan::output());
            } catch (Throwable $e) {
                $exit = 1;
                $output = $e->getMessage();
                report($e);
            }
            $failed += $exit === 0 ? 0 : 1;
            if ($output !== '' || $exit !== 0) {
                $this->line(($exit === 0 ? '<info>' : '<error>')."#{$tenant->id} {$tenant->name}".($exit === 0 ? '</info>' : '</error>'));
                if ($output !== '') {
                    $this->line($output);
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Provisioned, not deleted businesses (optionally only the given IDs). */
    public static function tenants(array $ids = []): Collection
    {
        return Tenant::query()
            ->whereNotNull('provisioned_at')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();
    }
}
