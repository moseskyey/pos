<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A business can work while on trial, paid up, or in the short grace period
 * after expiry. Otherwise every page leads to the billing page.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        if (! $tenant) {
            return redirect()->route('login');
        }

        // Platform admins helping a business can always get in.
        if ($tenant->canUseApp() || $request->session()->has('admin_impersonator_id')) {
            if (! $tenant->last_activity_at || $tenant->last_activity_at->lt(now()->subMinutes(10))) {
                $tenant->forceFill(['last_activity_at' => now()])->saveQuietly();
            }

            return $next($request);
        }

        $message = $tenant->status() === $tenant::SUSPENDED
            ? __('This business account is suspended. Please contact support.')
            : __('Your subscription has expired. Renew it to continue.');

        // Livewire re-runs this as persistent middleware and only stops on a redirect.
        if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => $message, 'billing_url' => route('billing.index')], 402);
        }

        return redirect()->route('billing.index')->with('error', $message);
    }
}
