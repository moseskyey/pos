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
        if (! $this->user()->can('customers.credit')) {
            // These fields are read-only for this user; keep the current values.
            $customer = $this->route('customer');
            $this->merge(['type' => $customer?->type ?? 'retail', 'credit_limit' => $customer?->credit_limit ?? 0]);
            $this->request->remove('opening_balance');
        }
        $this->merge([
            'phone' => $this->filled('phone') ? (PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')) : null,
            'credit_limit' => $this->filled('credit_limit') && is_scalar($this->input('credit_limit')) ? str_replace(',', '', (string) $this->input('credit_limit')) : ($this->route('customer')?->credit_limit ?? 0),
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

    /**
     * Validated data, minus fields the user may not set: credit limit,
     * wholesale status and opening balance need customers.credit.
     */
    public function customerData(): array
    {
        $data = $this->validated();
        if (! $this->user()->can('customers.credit')) {
            unset($data['credit_limit'], $data['type'], $data['opening_balance']);
            if (! $this->route('customer')) {
                $data += ['credit_limit' => 0, 'type' => 'retail', 'opening_balance' => 0];
            }
        }

        return $data;
    }

    public function messages(): array
    {
        return ['phone.regex' => __('Enter a valid Tanzanian mobile number, e.g. 0712 345 678.')];
    }
}
