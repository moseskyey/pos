<?php

use App\Models\Branch;
use App\Models\Platform\Tenant;
use App\Services\SettingsService;
use App\Support\BranchContext;
use App\Support\Features;
use App\Support\Money;
use App\Support\PlatformSettings;
use App\Support\Qty;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service : $service->get($key, $default);
    }
}

if (! function_exists('feature')) {
    /** Whether an optional module is switched on for this business (Settings → Features). */
    function feature(string $feature): bool
    {
        return Features::enabled($feature);
    }
}

if (! function_exists('tenant')) {
    /** The business the current request or job belongs to. */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current();
    }
}

if (! function_exists('device_key')) {
    /**
     * Key for data a browser keeps for the signed-in user (offline sales, cart
     * backup). It includes the business: user IDs repeat across businesses.
     */
    function device_key(): string
    {
        return (tenant() ? tenant()->id.'-' : '').auth()->id();
    }
}

if (! function_exists('legacy_device_key')) {
    /** The key used before multi-business, for the adopted business only (to move data forward). */
    function legacy_device_key(): ?string
    {
        $tenant = tenant();

        return $tenant && (int) PlatformSettings::get('legacy_tenant_id') === $tenant->id ? (string) auth()->id() : null;
    }
}

if (! function_exists('money')) {
    function money(mixed $value, bool $withSymbol = true): string
    {
        return Money::format($value, $withSymbol);
    }
}

if (! function_exists('qty')) {
    function qty(mixed $value): string
    {
        return Qty::display($value);
    }
}

if (! function_exists('branch_context')) {
    function branch_context(): BranchContext
    {
        return app(BranchContext::class);
    }
}

if (! function_exists('current_branch')) {
    function current_branch(): ?Branch
    {
        return app(BranchContext::class)->current();
    }
}

if (! function_exists('format_date')) {
    function format_date(mixed $date, bool $withTime = false): string
    {
        if (! $date) {
            return '—';
        }
        $date = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $format = setting('locale.date_format', 'd/m/Y');

        return $date->format($withTime ? $format.' H:i' : $format);
    }
}
