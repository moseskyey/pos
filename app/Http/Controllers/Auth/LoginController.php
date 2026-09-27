<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticateUser();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa_pending', true);

            return redirect()->route('two-factor.challenge');
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        activity('auth')->causedBy($user)->performedOn($user)->withProperties(['ip' => $request->ip()])->log('Logged in');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            activity('auth')->causedBy($user)->performedOn($user)->log('Logged out');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
