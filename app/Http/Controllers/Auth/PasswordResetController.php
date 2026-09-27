<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantLogin;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = $this->forBusinessOf($request->string('email'))
            ? Password::sendResetLink($request->only('email'))
            : Password::INVALID_USER;

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function reset(Request $request): View
    {
        $email = $request->query('email');

        return view('auth.reset-password', ['token' => (string) $request->route('token'), 'email' => is_string($email) ? $email : '']);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = ! $this->forBusinessOf($request->string('email')) ? Password::INVALID_USER : Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    /** Select the business the email belongs to. */
    private function forBusinessOf(string $email): bool
    {
        $entry = TenantLogin::where('email', strtolower(trim($email)))->first();
        $tenant = $entry ? Tenant::find($entry->tenant_id) : null;
        if (! $tenant) {
            return false;
        }
        app(TenantManager::class)->initialize($tenant);

        return true;
    }
}
