<?php

namespace App\Http\Requests;

use App\Http\Controllers\LabelController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.labels');
    }

    public function rules(): array
    {
        return [
            'size' => ['required', Rule::in(array_keys(LabelController::SIZES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'show_price' => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
        ];
    }
}
