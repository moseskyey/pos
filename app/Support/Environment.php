<?php

namespace App\Support;

/**
 * Guards against running a public server with development settings
 * (APP_ENV=local / APP_DEBUG=true), which exposes stack traces and source.
 */
final class Environment
{
    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.localhost') || str_ends_with($host, '.local');
    }

    /** Problems an owner should fix on this server, for a warning banner. */
    public static function warnings(string $host): array
    {
        if (self::isLocalHost($host)) {
            return [];
        }
        $warnings = [];
        if (! app()->isProduction()) {
            $warnings[] = __('The server is running in :env mode. Set APP_ENV=production in .env.', ['env' => app()->environment()]);
        }
        if (config('app.debug_forced_off') || config('app.debug')) {
            $warnings[] = __('Debug mode is switched on in .env. Set APP_DEBUG=false (DukaPOS hides error details on this public address anyway).');
        }

        return $warnings;
    }
}
