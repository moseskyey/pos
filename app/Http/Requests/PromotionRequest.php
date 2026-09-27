<?php

namespace App\Http\Requests;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        return $promotion ? $this->user()->can('update', $promotion) : $this->user()->can('create', Promotion::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['start_time', 'end_time', 'starts_on', 'ends_on', 'min_qty', 'buy_qty', 'get_qty'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Promotion::TYPES)],
            'value' => [Rule::requiredIf(in_array($type, ['percent', 'amount', 'multi_price'], true)), 'nullable', 'numeric', 'min:0',
                $type === 'percent' ? 'max:100' : 'max:9999999999'],
            'buy_qty' => [Rule::requiredIf(in_array($type, ['buy_get', 'multi_price'], true)), 'nullable', 'integer', 'min:1', 'max:1000'],
            'get_qty' => [Rule::requiredIf($type === 'buy_get'), 'nullable', 'integer', 'min:1', 'max:1000'],
            'min_qty' => ['nullable', 'numeric', 'min:0'],
            'applies_to' => ['required', Rule::in(['all', 'categories', 'products'])],
            'product_ids' => [Rule::requiredIf($this->input('applies_to') === 'products'), 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'category_ids' => [Rule::requiredIf($this->input('applies_to') === 'categories'), 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'different:start_time'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'priority' => ['nullable', 'integer', 'between:0,100'],
            'is_active' => ['boolean'],
        ];
    }

    /** Validated data with the fields the chosen type doesn't use cleared. */
    public function promotionData(): array
    {
        $data = $this->validated();
        $data['value'] ??= 0;
        $data['priority'] ??= 0;
        if (! in_array($data['type'], ['buy_get', 'multi_price'], true)) {
            $data['buy_qty'] = null;
        }
        if ($data['type'] !== 'buy_get') {
            $data['get_qty'] = null;
        } else {
            $data['value'] = 0;
        }

        return $data;
    }
}
