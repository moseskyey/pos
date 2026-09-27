<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('stock.adjust');
    }

    public static function rulesFor(): array
    {
        return [
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.direction' => ['required', Rule::in(['in', 'out'])],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_no' => ['nullable', 'string', 'max:64'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    public function rules(): array
    {
        return static::rulesFor();
    }
}
