<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use App\Models\Platform\TenantLogin;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TenantStoreRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $this->filled('phone') ? (PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^255[67]\d{8}$/'],
            'password' => ['nullable', 'string', 'min:8'],
            'plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')],
            'trial_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (TenantLogin::where('email', $this->input('email'))->exists()) {
                $validator->errors()->add('email', __('This email is already used by another account.'));
            }
            if ($this->input('phone') && TenantLogin::where('phone', $this->input('phone'))->exists()) {
                $validator->errors()->add('phone', __('This phone number is already used by another account.'));
            }
        }];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
