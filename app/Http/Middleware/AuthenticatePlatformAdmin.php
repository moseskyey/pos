<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform admin area: central database only, active admins only.
 * "admin:super" also requires a super admin (settings, other admins).
 */
class AuthenticatePlatformAdmin
{
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        // A business may be selected in this session (admin viewing a shop); the admin area never uses it.
        $tenancy = app(TenantManager::class);
        $previous = $tenancy->current();
        $tenancy->end();

        try {
            return $this->authorizeAdmin($request, $next, $level);
        } finally {
            // Same process, next request (tests, queue "sync"): leave things as they were.
            if ($previous) {
                $tenancy->initialize($previous);
            }
        }
    }

    protected function authorizeAdmin(Request $request, Closure $next, ?string $level): Response
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            return $request->expectsJson() ? response()->json(['message' => 'Unauthenticated.'], 401) : redirect()->guest(route('admin.login'));
        }
        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')->withErrors(['email' => __('Your admin account has been deactivated.')]);
        }
        abort_if($level === 'super' && ! $admin->is_super, 403, __('Only super admins can do this.'));

        return $next($request);
    }
}
