<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\SupplierBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', SupplierBill::class);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank' => ['nullable', 'string', 'max:80'],
            'cheque_date' => ['nullable', 'date'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'from_drawer' => ['boolean'],
            'allocations' => ['array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
