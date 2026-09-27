<?php

namespace App\Providers;

use App\Services\SettingsService;
use App\Support\BranchContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->scoped(BranchContext::class);
    }

    public function boot(): void
    {
        // Strict lazy-loading only while developing; never turns a live page into a 500.
        Model::preventLazyLoading($this->app->environment('local', 'testing') && config('app.debug'));
        Model::preventAccessingMissingAttributes(false);
        Paginator::useBootstrapFive();

        // Owner / Super Admin can do everything.
        Gate::before(fn ($user) => $user->hasRole('owner') ? true : null);

        $this->applyLocaleSettings();
        $this->configureRateLimiting();
    }

    protected function applyLocaleSettings(): void
    {
        $timezone = setting('locale.timezone', config('app.timezone'));
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('login')).'|'.$request->ip()));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('callbacks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
