<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\Platform\AdminActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $key = 'admin-login|'.strtolower($request->string('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $credentials = ['email' => strtolower($request->string('email')), 'password' => $request->string('password'), 'is_active' => true];
        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();
        AdminActivity::record('auth.login', 'Signed in');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AdminActivity::record('auth.logout', 'Signed out');
        Auth::guard('admin')->logout();
        // Leave any business the admin opened as well.
        if ($request->session()->pull('admin_impersonator_id')) {
            Auth::guard('web')->logout();
            $request->session()->forget('tenant_id');
        }
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
