<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\PhoneNumber;
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

    /** Resolve and verify the user without logging them in. */
    public function authenticateUser(): User
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login'));
        $query = User::query();
        if (str_contains($login, '@')) {
            $query->where('email', strtolower($login));
        } else {
            $query->where('phone', PhoneNumber::normalize($login) ?? $login);
        }
        $user = $query->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);
            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('Your account has been deactivated.')]);
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
