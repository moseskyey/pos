<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.edit_price');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'field' => ['required', Rule::in(['retail_price', 'wholesale_price', 'both'])],
            'mode' => ['required', Rule::in(['percent', 'fixed', 'set'])],
            'value' => ['required', 'numeric', 'between:-1000000000,1000000000'],
            'rounding' => ['nullable', Rule::in(['0', '50', '100'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
