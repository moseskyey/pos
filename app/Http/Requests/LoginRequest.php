<?php

namespace App\Http\Requests;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantLogin;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => __('email or phone')];
    }

    /**
     * Resolve and verify the user without logging them in. The email or phone
     * identifies the business; its database becomes the active one.
     */
    public function authenticateUser(): User
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login'));
        [$field, $value] = str_contains($login, '@')
            ? ['email', strtolower($login)]
            : ['phone', PhoneNumber::normalize($login) ?? $login];

        $entry = TenantLogin::where($field, $value)->first();
        $tenant = $entry ? Tenant::find($entry->tenant_id) : null;
        $user = null;
        if ($tenant) {
            app(TenantManager::class)->initialize($tenant);
            $user = User::whereKey($entry->user_id)->where($field, $value)->first();
        }

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);
            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('Your account has been deactivated.')]);
        }

        if ($tenant->status() === Tenant::SUSPENDED) {
            throw ValidationException::withMessages(['login' => __('This business account is suspended. Please contact support.')]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
