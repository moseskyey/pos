<?php

namespace App\Http\Requests;

use App\Models\SupplierBill;
use Illuminate\Foundation\Http\FormRequest;

class SupplierBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierBill::class);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'bill_no' => ['nullable', 'string', 'max:64'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'total' => ['required', 'numeric', 'gt:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0', 'lte:total'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
