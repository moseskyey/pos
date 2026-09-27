<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reorder', PurchaseOrder::class);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.selected' => ['nullable', 'boolean'],
        ];
    }
}
