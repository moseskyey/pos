<?php

namespace App\Http\Requests;

use App\Models\Platform\Plan;
use App\Models\Platform\TenantLogin;
use App\Support\PhoneNumber;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) PlatformSettings::get('signups_enabled');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^255[67]\d{8}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'terms' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (TenantLogin::where('email', $this->input('email'))->exists()) {
                $validator->errors()->add('email', __('An account with this email already exists. Sign in instead.'));
            }
            if (TenantLogin::where('phone', $this->input('phone'))->exists()) {
                $validator->errors()->add('phone', __('This phone number is already used by another account.'));
            }
        }];
    }

    public function attributes(): array
    {
        return ['business_name' => __('business name'), 'owner_name' => __('your name'), 'terms' => __('terms')];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
