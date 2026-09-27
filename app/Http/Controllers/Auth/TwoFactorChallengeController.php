<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('2fa_pending')) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor');
    }

    public function store(TwoFactorCodeRequest $request, Google2FA $google2fa): RedirectResponse
    {
        $user = $request->user();
        $key = '2fa:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! $google2fa->verifyKey($user->two_factor_secret, $request->input('code'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => __('The code is invalid.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget('2fa_pending');
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        activity('auth')->causedBy($user)->performedOn($user)->log('Logged in (2FA)');

        return redirect()->intended(route('dashboard'));
    }
}
