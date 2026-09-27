<?php

use App\Models\Branch;
use App\Services\SettingsService;
use App\Support\BranchContext;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service : $service->get($key, $default);
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
