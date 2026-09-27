<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $this->filled('phone') ? (PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^255[67]\d{8}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::exists('roles', 'name')],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')],
            'default_branch_id' => ['nullable', 'integer', Rule::in($this->input('branches', []))],
            'pin' => ['nullable', 'digits_between:4,6'],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a valid Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
