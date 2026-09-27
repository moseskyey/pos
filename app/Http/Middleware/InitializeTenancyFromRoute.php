<?php

namespace App\Http\Middleware;

use App\Models\Platform\Tenant;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, signed links and payment webhooks carry the business in the URL
 * (/t/{tenant}/…). Links made before multi-tenancy have no business in them
 * and resolve to the business that was adopted from the old install.
 */
class InitializeTenancyFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $id = $route->parameter('tenant') ?? PlatformSettings::get('legacy_tenant_id');
        $route->forgetParameter('tenant');

        $tenant = is_numeric($id) ? Tenant::find((int) $id) : null;
        abort_unless($tenant, 404);

        app(TenantManager::class)->initialize($tenant);
        if (! $request->hasSession()) {
            app()->setLocale(in_array($locale = setting('locale.language', 'en'), SetLocale::SUPPORTED, true) ? $locale : 'en');
            View::share('errors', new ViewErrorBag);
        }

        return $next($request);
    }
}
