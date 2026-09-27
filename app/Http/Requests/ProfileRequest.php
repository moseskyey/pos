<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($this->user()->id)],
            'phone' => ['nullable', 'regex:/^255[67]\d{8}$/', Rule::unique('users')->ignore($this->user()->id)],
            'locale' => ['required', Rule::in(['en', 'sw'])],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a valid Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
