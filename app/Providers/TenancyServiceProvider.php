<?php

namespace App\Providers;

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Platform\Tenant;
use App\Services\Platform\PlanLimits;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use RuntimeException;

/**
 * Database-per-business tenancy: the tenant manager, and carrying the current
 * business through the queue so jobs run against the right database.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /** Businesses active before each job started (jobs can run nested on the sync queue). */
    protected array $stack = [];

    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->scoped(PlatformSettings::class);
    }

    public function boot(): void
    {
        PlanLimits::register();

        // Livewire actions re-check these, so an open page stops working when the
        // subscription ends or the user is deactivated (not only on the next page load).
        Livewire::addPersistentMiddleware([EnsureSubscriptionActive::class, EnsureUserIsActive::class]);

        Queue::createPayloadUsing(function () {
            $tenant = $this->app->make(TenantManager::class)->current();

            return $tenant ? ['tenant_id' => $tenant->getKey()] : [];
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $tenancy = $this->app->make(TenantManager::class);
            $this->stack[] = $tenancy->current();
            $id = $event->job->payload()['tenant_id'] ?? null;

            if ($id === null) {
                $tenancy->end();

                return;
            }

            $tenant = Tenant::find($id);
            if (! $tenant) {
                throw new RuntimeException("Business #{$id} no longer exists; job skipped.");
            }
            $tenancy->initialize($tenant);
        });

        $restore = function () {
            if (! $this->stack) {
                return;
            }
            $previous = array_pop($this->stack);
            $tenancy = $this->app->make(TenantManager::class);
            $previous ? $tenancy->initialize($previous) : $tenancy->end();
        };
        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }
}
