<?php

namespace App\Http\Middleware;

use App\Models\Platform\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects the signed-in user's business (from the session, or the tenant
 * cookie when "Remember me" outlives the session) before authentication runs.
 */
class IdentifyTenant
{
    public function __construct(protected TenantManager $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $session = $request->session();
        $recaller = $guard->getRecallerName();
        $cookie = config('tenancy.cookie');

        $id = $session->get('tenant_id');
        if (! $id && $request->cookies->has($recaller)) {
            $id = $request->cookie($cookie);
        }
        $tenant = is_numeric($id) ? Tenant::find((int) $id) : null;

        // Signed in before multi-tenancy was installed: the one existing business.
        if (! $tenant && ! $id && $session->has($guard->getName()) && Tenant::count() === 1) {
            $tenant = Tenant::first();
        }

        // Already chosen earlier in this process (tests, console-dispatched requests).
        $tenant ??= $id ? null : $this->tenancy->current();

        if ($tenant) {
            $this->tenancy->initialize($tenant);
            $session->put('tenant_id', $tenant->id);
        } else {
            // No business: nothing to authenticate a shop user against.
            $session->forget(['tenant_id', $guard->getName()]);
            if ($request->cookies->has($recaller)) {
                $request->cookies->remove($recaller);
                Cookie::queue(Cookie::forget($recaller));
            }
        }

        return $next($request);
    }
}
