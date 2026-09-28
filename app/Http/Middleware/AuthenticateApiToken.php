<?php

namespace App\Http\Middleware;

use App\Models\Platform\Tenant;
use App\Services\ApiTokenService;
use App\Support\BranchContext;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for /api/v1. The token names its business, so
 * the business database is selected first and the token is checked there.
 * The user's own permissions and branches apply to every endpoint.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $tenant = ($id = ApiTokenService::tenantIdFrom($plain)) ? Tenant::find($id) : null;
        if (! $tenant) {
            return $this->deny(__('Missing or invalid API token.'));
        }
        app(TenantManager::class)->initialize($tenant);

        if (! $tenant->canUseApp()) {
            return response()->json(['message' => __('Your subscription has expired. Renew it to continue.')], 402);
        }
        if (! feature('api')) {
            return response()->json(['message' => __('API access is switched off for this business.')], 403);
        }

        $token = app(ApiTokenService::class)->find($plain);
        if (! $token || $token->isExpired() || ! $token->user?->is_active) {
            return $this->deny(__('Missing or invalid API token.'));
        }
        if (! $request->isMethodSafe() && ! $token->can('write')) {
            return response()->json(['message' => __('This token can only read.')], 403);
        }

        Auth::guard('web')->setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        // ?branch_id= narrows to one branch the user may see; otherwise all of them.
        $context = app(BranchContext::class);
        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId && ! $context->canAccess($branchId)) {
            return response()->json(['message' => __('You do not have access to that branch.')], 403);
        }
        $context->useForApi($branchId);

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }

    protected function deny(string $message): Response
    {
        return response()->json(['message' => $message], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
