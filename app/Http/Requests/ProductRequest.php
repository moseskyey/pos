<?php

namespace App\Http\Requests;

use App\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product ? $this->user()->can('products.edit') : $this->user()->can('products.create');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? str_replace([',', ' '], '', $v) : $v;
        $this->merge([
            'cost_price' => $clean($this->input('cost_price')),
            'retail_price' => $clean($this->input('retail_price')),
            'wholesale_price' => $clean($this->input('wholesale_price')),
            'barcodes' => array_values(array_filter((array) $this->input('barcodes', []), fn ($b) => trim((string) $b) !== '')),
            'units' => array_values(array_filter((array) $this->input('units', []), fn ($u) => ! empty($u['unit_id']))),
            'variants' => array_values(array_filter((array) $this->input('variants', []), fn ($v) => collect($v['attributes'] ?? [])->filter()->isNotEmpty())),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $variantIds = $product ? $product->variants()->pluck('id')->all() : [];
        $ownIds = [$product?->id, ...$variantIds];

        return [
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'retail_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'wholesale_min_qty' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['required', Rule::enum(TaxType::class)],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'track_batches' => ['boolean'],
            'is_weighted' => ['boolean'],
            'has_variants' => ['boolean'],
            'is_bundle' => ['boolean', Rule::prohibitedIf($this->boolean('has_variants') && $this->boolean('is_bundle'))],
            'bundle_items' => [Rule::requiredIf($this->boolean('is_bundle')), 'array'],
            'bundle_items.*.component_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'bundle_items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'price_reason' => ['nullable', 'string', 'max:255'],

            'barcodes' => ['array'],
            'barcodes.*' => ['string', 'max:64', 'distinct', Rule::unique('product_barcodes', 'barcode')->where(fn ($q) => $q->whereNotIn('product_id', array_filter($ownIds)))],

            'units' => ['array'],
            'units.*.unit_id' => ['required', 'distinct', Rule::exists('units', 'id'), 'different:unit_id'],
            'units.*.factor' => ['required', 'numeric', 'gt:0'],
            'units.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'distinct', Rule::unique('product_barcodes', 'barcode')->where(fn ($q) => $q->whereNotIn('product_id', array_filter($ownIds)))],

            'variants' => [Rule::requiredIf($this->boolean('has_variants')), 'array'],
            'variants.*.id' => ['nullable', Rule::in($variantIds)],
            'variants.*.attributes' => ['array'],
            'variants.*.attributes.*' => ['nullable', 'string', 'max:40'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64', 'distinct', Rule::unique('product_barcodes', 'barcode')->where(fn ($q) => $q->whereNotIn('product_id', array_filter($ownIds)))],
            'variants.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'barcodes.*' => __('barcode'),
            'units.*.unit_id' => __('unit'),
            'units.*.factor' => __('conversion'),
            'units.*.barcode' => __('unit barcode'),
            'variants.*.barcode' => __('variant barcode'),
            'variants.*.sku' => __('variant SKU'),
        ];
    }

    public function messages(): array
    {
        return ['units.*.unit_id.different' => __('A secondary unit must differ from the base unit.')];
    }
}
