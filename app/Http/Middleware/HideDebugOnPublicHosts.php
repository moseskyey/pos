<?php

namespace App\Http\Middleware;

use App\Support\Environment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Never show stack traces (or throw development-only errors) on a public
 * address, even if .env was left with APP_DEBUG=true / APP_ENV=local.
 */
class HideDebugOnPublicHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.debug_remote') && ! Environment::isLocalHost($request->getHost())) {
            if (config('app.debug')) {
                config(['app.debug' => false, 'app.debug_forced_off' => true]);
            }
            Model::preventLazyLoading(false);
        }

        return $next($request);
    }
}
