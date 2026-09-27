<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canAny(['suppliers.manage', 'purchases.manage']);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'supplier_sku' => ['nullable', 'string', 'max:64'],
            'lead_time_days' => ['nullable', 'integer', 'between:0,365'],
            'is_preferred' => ['boolean'],
        ];
    }
}
