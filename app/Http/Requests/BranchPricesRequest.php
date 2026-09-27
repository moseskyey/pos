<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BranchPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.edit_price') && $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $prices = collect($this->input('prices', []))->map(fn ($row) => [
            'retail_price' => is_scalar($row['retail_price'] ?? null) && $row['retail_price'] !== '' ? str_replace(',', '', (string) $row['retail_price']) : null,
            'wholesale_price' => is_scalar($row['wholesale_price'] ?? null) && $row['wholesale_price'] !== '' ? str_replace(',', '', (string) $row['wholesale_price']) : null,
        ]);
        $this->merge(['prices' => $prices->all()]);
    }

    public function rules(): array
    {
        return [
            'prices' => ['array'],
            'prices.*.retail_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'prices.*.wholesale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowed = branch_context()->accessibleIds();
            foreach (array_keys($this->input('prices', [])) as $branchId) {
                if (! in_array((int) $branchId, $allowed, true)) {
                    $validator->errors()->add('prices', __('You cannot set prices for that branch.'));
                }
            }
        });
    }
}
