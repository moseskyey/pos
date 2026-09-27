<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Qty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcodes' => $this->whenLoaded('barcodes', fn () => $this->barcodes->pluck('barcode')),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit?->short_name),
            'retail_price' => (string) $this->retail_price,
            'wholesale_price' => $this->wholesale_price !== null ? (string) $this->wholesale_price : null,
            'wholesale_min_qty' => $this->wholesale_min_qty !== null ? (string) $this->wholesale_min_qty : null,
            'cost_price' => $this->when($request->user()->can('products.view_cost'), fn () => (string) $this->cost_price),
            'tax_type' => $this->tax_type?->value,
            'track_stock' => $this->track_stock,
            'stock' => $this->when(array_key_exists('stock_qty', $this->resource->getAttributes()), fn () => $this->track_stock ? Qty::round($this->stock_qty ?? 0) : null),
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
