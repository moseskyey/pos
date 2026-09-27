<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrentPasswordRequest;
use App\Http\Requests\LocaleRequest;
use App\Http\Requests\PinRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\ThemeRequest;
use App\Http\Requests\TwoFactorCodeRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePinRequest;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function edit(Request $request, Google2FA $google2fa): View
    {
        $user = $request->user();
        $qr = null;
        $pendingSecret = $request->session()->get('2fa_setup_secret');
        if ($pendingSecret) {
            $qr = QrCode::svg($google2fa->getQRCodeUrl(setting('business.name', 'DukaPOS'), $user->email, $pendingSecret), 180);
        }

        return view('profile.edit', ['user' => $user, 'qr' => $qr, 'pendingSecret' => $pendingSecret]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'email', 'phone', 'locale']));
        if ($request->hasFile('avatar')) {
            $user->deleteAvatar();
            $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'local')]);
        }

        return back()->with('success', __('Profile updated.'));
    }

    public function password(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->input('password')]);
        activity('auth')->performedOn($request->user())->log('Password changed');

        return back()->with('success', __('Password changed.'));
    }

    public function pin(UpdatePinRequest $request): RedirectResponse
    {
        $request->user()->setPin($request->input('pin'));
        activity('auth')->performedOn($request->user())->log('PIN changed');

        return back()->with('success', __('PIN updated.'));
    }

    public function theme(ThemeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $request->user()->forceFill(['theme' => $data['theme']])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    public function locale(LocaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $request->session()->put('locale', $data['locale']);
        $request->user()?->forceFill(['locale' => $data['locale']])->saveQuietly();

        return back();
    }

    public function enableTwoFactor(CurrentPasswordRequest $request, Google2FA $google2fa): RedirectResponse
    {
        $request->session()->put('2fa_setup_secret', $google2fa->generateSecretKey());

        return back()->with('info', __('Scan the QR code with your authenticator app, then enter the code to confirm.'));
    }

    public function confirmTwoFactor(TwoFactorCodeRequest $request, Google2FA $google2fa): RedirectResponse
    {
        $secret = $request->session()->get('2fa_setup_secret');
        abort_unless($secret, 400);

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('The code is invalid.')], 'twofactor');
        }

        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa_setup_secret');
        activity('auth')->performedOn($request->user())->log('Two-factor authentication enabled');

        return back()->with('success', __('Two-factor authentication enabled.'));
    }

    public function disableTwoFactor(CurrentPasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        activity('auth')->performedOn($request->user())->log('Two-factor authentication disabled');

        return back()->with('success', __('Two-factor authentication disabled.'));
    }

    public function verifyPin(PinRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->pinLocked()) {
            return response()->json(['ok' => false, 'message' => __('Too many attempts. Sign in again.')], 423);
        }
        if (! $user->hasPin() ? ! Hash::check($request->input('pin'), $user->password) : ! Hash::check($request->input('pin'), $user->pin)) {
            $user->increment('pin_attempts');
            if ($user->pin_attempts >= 5) {
                $user->forceFill(['pin_locked_until' => now()->addMinutes(15)])->save();
            }

            return response()->json(['ok' => false, 'message' => __('Incorrect PIN.')], 422);
        }
        $user->forceFill(['pin_attempts' => 0])->saveQuietly();
        $request->session()->forget('pos_locked');

        return response()->json(['ok' => true]);
    }

    /**
     * Flag the terminal as locked so a reload keeps the lock screen up.
     */
    public function lock(Request $request): JsonResponse
    {
        $request->session()->put('pos_locked', true);

        return response()->json(['ok' => true]);
    }
}
