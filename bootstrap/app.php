<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AuthenticatePlatformAdmin;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HideDebugOnPublicHosts;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\InitializeTenancyFromRoute;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Public signed links (receipt verification, shared PDFs): no session, business from the URL.
            Route::middleware('tenant.route')->group(base_path('routes/public.php'));
            // Platform administration (central database only).
            Route::middleware('web')->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HideDebugOnPublicHosts::class);
        $middleware->web(append: [IdentifyTenant::class, SetLocale::class, SecurityHeaders::class]);
        // The business must be chosen before authentication and route model binding.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyTenant::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, InitializeTenancyFromRoute::class);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'subscribed' => EnsureSubscriptionActive::class,
            'tenant.route' => InitializeTenancyFromRoute::class,
            'admin' => AuthenticatePlatformAdmin::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('dashboard'));
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business rules that escaped a controller (e.g. a plan limit hit from a model event).
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput($request->except('password', 'password_confirmation', 'pin'))->with('error', $e->getMessage());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
