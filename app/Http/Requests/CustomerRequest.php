<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->filled('phone') ? (PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')) : null,
            'credit_limit' => $this->input('credit_limit') !== null ? str_replace(',', '', (string) $this->input('credit_limit')) : 0,
        ]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'regex:/^255[67]\d{8}$/', Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'email' => ['nullable', 'email', 'max:160'],
            'tin' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['retail', 'wholesale'])],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => [$customer ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a valid Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
