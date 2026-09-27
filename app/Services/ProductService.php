<?php

namespace App\Services;

use App\Models\BranchPrice;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use App\Support\BarcodeParser;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public const PRICE_FIELDS = ['cost_price', 'retail_price', 'wholesale_price'];

    public const COPY_TO_VARIANTS = ['category_id', 'brand_id', 'unit_id', 'tax_type', 'reorder_level', 'track_stock', 'track_batches', 'description'];

    /**
     * Create a product with barcodes, secondary units and variants.
     */
    public function create(array $data, User $user, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $user, $image) {
            $data = $this->normalizeBundle($this->filterPrices($data, $user));
            $product = new Product(Arr::only($data, (new Product)->getFillable()));
            $product->sku = $data['sku'] ?? null ?: 'TMP-'.Str::uuid();
            $product->save();

            if (str_starts_with($product->sku, 'TMP-')) {
                $product->update(['sku' => $this->generateSku($product)]);
            }

            if ($image) {
                $product->update(['image_path' => $image->store('products', 'local')]);
            }

            $this->syncBarcodes($product, $data['barcodes'] ?? []);
            $this->syncUnits($product, $data['units'] ?? []);
            if (! empty($data['has_variants'])) {
                $this->syncVariants($product, $data['variants'] ?? [], $user);
            }
            if (! empty($data['is_bundle'])) {
                $this->syncBundle($product, $data['bundle_items'] ?? [], $user);
            }

            return $product->fresh(['barcodes', 'units', 'variants']);
        });
    }

    public function update(Product $product, array $data, User $user, ?UploadedFile $image = null, ?string $reason = null): Product
    {
        return DB::transaction(function () use ($product, $data, $user, $image, $reason) {
            $data = $this->normalizeBundle($this->filterPrices($data, $user, $product));
            $before = $product->only(self::PRICE_FIELDS);

            $attributes = Arr::only($data, (new Product)->getFillable());
            if (empty($attributes['sku'])) {
                unset($attributes['sku']);
            }
            $product->fill($attributes)->save();

            $this->logPriceChanges($product, $before, $user, $reason);

            if ($image) {
                if ($product->image_path) {
                    Storage::disk('local')->delete($product->image_path);
                }
                $product->update(['image_path' => $image->store('products', 'local')]);
            }

            if (array_key_exists('barcodes', $data)) {
                $this->syncBarcodes($product, $data['barcodes'] ?? []);
            }
            if (array_key_exists('units', $data)) {
                $this->syncUnits($product, $data['units'] ?? []);
            }
            if ($product->has_variants) {
                if (array_key_exists('variants', $data)) {
                    $this->syncVariants($product, $data['variants'] ?? [], $user);
                }
                $product->variants()->update(Arr::only($product->only(self::COPY_TO_VARIANTS), self::COPY_TO_VARIANTS));
            }
            if ($product->is_bundle && array_key_exists('bundle_items', $data)) {
                $this->syncBundle($product, $data['bundle_items'] ?? [], $user);
            } elseif (! $product->is_bundle) {
                $product->bundleItems()->delete();
            }

            return $product->fresh(['barcodes', 'units', 'variants']);
        });
    }

    /** A bundle's stock lives in its components: it never tracks its own stock, batches or variants. */
    protected function normalizeBundle(array $data): array
    {
        if (! empty($data['is_bundle'])) {
            $data['track_stock'] = false;
            $data['track_batches'] = false;
            $data['has_variants'] = false;
            $data['is_weighted'] = false;
        }

        return $data;
    }

    /**
     * Replace a bundle's components and set its cost to theirs.
     *
     * @param  array<int, array{component_id: mixed, quantity: mixed}>  $rows
     */
    protected function syncBundle(Product $bundle, array $rows, User $user): void
    {
        $rows = collect($rows)->filter(fn ($r) => ! empty($r['component_id']) && Qty::isPositive($r['quantity'] ?? 0))
            ->keyBy(fn ($r) => (int) $r['component_id']);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['bundle_items' => __('Add at least one item to the bundle.')]);
        }
        $components = Product::query()->whereIn('id', $rows->keys())->get()->keyBy('id');
        foreach ($rows->keys() as $id) {
            $component = $components->get($id);
            if (! $component || $component->id === $bundle->id || $component->is_bundle || $component->has_variants) {
                throw ValidationException::withMessages(['bundle_items' => __('Bundle items must be ordinary products (not bundles, the bundle itself, or products with variants).')]);
            }
        }

        $bundle->bundleItems()->delete();
        foreach ($rows as $id => $row) {
            $bundle->bundleItems()->create(['component_id' => $id, 'quantity' => Qty::round($row['quantity'])]);
        }
        $cost = Money::sum($rows->map(fn ($r, $id) => Money::mul($r['quantity'], $components[$id]->cost_price)));
        $this->changePrices($bundle, ['cost_price' => $cost], $user, __('Bundle cost from its items'));
    }

    /**
     * Save per-branch selling prices; an empty retail price removes the override.
     *
     * @param  array<int|string, array{retail_price: ?string, wholesale_price: ?string}>  $prices  branch id => prices
     */
    public function saveBranchPrices(Product $product, array $prices, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($product, $prices, $user, $reason) {
            foreach ($prices as $branchId => $row) {
                $existing = BranchPrice::query()->where('branch_id', $branchId)->where('product_id', $product->id)->whereNull('product_unit_id')->first();
                if ($row['retail_price'] === null) {
                    $existing?->delete();
                } else {
                    BranchPrice::query()->updateOrCreate(
                        ['branch_id' => $branchId, 'product_id' => $product->id, 'product_unit_id' => null],
                        ['retail_price' => Money::round($row['retail_price']), 'wholesale_price' => $row['wholesale_price'] === null ? null : Money::round($row['wholesale_price'])],
                    );
                }
                $changed = $existing?->retail_price != $row['retail_price'] || $existing?->wholesale_price != $row['wholesale_price'];
                if ($changed) {
                    activity('products')->causedBy($user)->performedOn($product)
                        ->withProperties(['branch_id' => (int) $branchId, 'old' => $existing?->only(['retail_price', 'wholesale_price']), 'new' => $row, 'reason' => $reason])
                        ->log('Branch price changed');
                }
            }
        });
    }

    /**
     * Set prices and write price history. Used by bulk updates and GRN costing.
     *
     * @param  array<string, mixed>  $prices
     */
    public function changePrices(Product $product, array $prices, ?User $user, ?string $reason = null): void
    {
        $before = $product->only(self::PRICE_FIELDS);
        $product->fill(Arr::only($prices, self::PRICE_FIELDS))->save();
        $this->logPriceChanges($product, $before, $user, $reason);
    }

    public function logPriceChanges(Product $product, array $before, ?User $user, ?string $reason = null): void
    {
        foreach (self::PRICE_FIELDS as $field) {
            $old = $before[$field] ?? null;
            $new = $product->{$field};
            if (($old === null && $new === null) || ($old !== null && $new !== null && Money::cmp($old, $new) === 0)) {
                continue;
            }
            PriceHistory::create([
                'product_id' => $product->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'user_id' => $user?->id,
                'reason' => $reason,
            ]);
        }
    }

    public function generateSku(Product $product): string
    {
        $prefix = $product->category ? strtoupper(Str::substr(Str::slug($product->category->name, ''), 0, 3)) : 'PRD';
        $sku = sprintf('%s-%05d', $prefix ?: 'PRD', $product->id);

        return Product::withTrashed()->where('sku', $sku)->whereKeyNot($product->id)->exists() ? $sku.'-'.Str::upper(Str::random(3)) : $sku;
    }

    /** Strip price fields the user may not edit. */
    protected function filterPrices(array $data, User $user, ?Product $existing = null): array
    {
        if (! $user->can('products.edit_price')) {
            foreach (['retail_price', 'wholesale_price', 'wholesale_min_qty'] as $field) {
                if ($existing) {
                    unset($data[$field]);
                } else {
                    $data[$field] ??= null;
                }
            }
            unset($data['units']);
            if ($existing === null) {
                $data['retail_price'] = $data['retail_price'] ?? 0;
            }
        }
        if (! $user->can('products.view_cost')) {
            unset($data['cost_price']);
        }
        // Blank price fields arrive as null; the columns are required, so blank means zero.
        foreach (['cost_price', 'retail_price'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }

        return $data;
    }

    protected function syncBarcodes(Product $product, array $barcodes): void
    {
        $barcodes = collect($barcodes)->map(fn ($b) => trim((string) $b))->filter()->unique()->values();
        $product->barcodes()->whereNull('product_unit_id')->whereNotIn('barcode', $barcodes)->delete();
        foreach ($barcodes as $barcode) {
            $product->barcodes()->firstOrCreate(['barcode' => $barcode, 'product_unit_id' => null]);
        }
    }

    protected function syncUnits(Product $product, array $units): void
    {
        $keep = [];
        foreach ($units as $row) {
            if (empty($row['unit_id']) || empty($row['factor'])) {
                continue;
            }
            $unit = $product->units()->updateOrCreate(
                ['unit_id' => $row['unit_id']],
                ['factor' => $row['factor'], 'retail_price' => $row['retail_price'] ?? 0, 'wholesale_price' => $row['wholesale_price'] ?? null]
            );
            $keep[] = $unit->id;
            $unit->barcodes()->delete();
            if (! empty($row['barcode'])) {
                $product->barcodes()->create(['barcode' => trim($row['barcode']), 'product_unit_id' => $unit->id]);
            }
        }
        $product->units()->whereNotIn('id', $keep)->each(function ($unit) {
            $unit->barcodes()->delete();
            $unit->delete();
        });
    }

    protected function syncVariants(Product $parent, array $variants, User $user): void
    {
        $keep = [];
        foreach ($variants as $row) {
            $attributes = array_filter((array) ($row['attributes'] ?? []), fn ($v) => $v !== null && $v !== '');
            if (! $attributes) {
                continue;
            }
            $label = collect($attributes)->join(' / ');
            $variant = ! empty($row['id']) ? $parent->variants()->find($row['id']) : null;
            // Same rules as the parent product: prices need products.edit_price, cost needs products.view_cost.
            // Without them a variant keeps its current values (or inherits the parent's when new).
            if (! $user->can('products.edit_price')) {
                $row['retail_price'] = $variant?->retail_price ?? $parent->retail_price;
                $row['wholesale_price'] = $variant ? $variant->wholesale_price : $parent->wholesale_price;
            }
            if (! $user->can('products.view_cost')) {
                $row['cost_price'] = $variant?->cost_price ?? $parent->cost_price;
            }
            $payload = array_merge($parent->only(self::COPY_TO_VARIANTS), [
                'parent_id' => $parent->id,
                'name' => $parent->name.' - '.$label,
                'variant_attributes' => $attributes,
                'retail_price' => $row['retail_price'] ?? $parent->retail_price,
                'wholesale_price' => $row['wholesale_price'] ?? $parent->wholesale_price,
                'wholesale_min_qty' => $parent->wholesale_min_qty,
                'cost_price' => $row['cost_price'] ?? $parent->cost_price,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'has_variants' => false,
            ]);

            if ($variant) {
                $before = $variant->only(self::PRICE_FIELDS);
                if (! empty($row['sku'])) {
                    $payload['sku'] = $row['sku'];
                }
                $variant->fill($payload)->save();
                $this->logPriceChanges($variant, $before, $user);
            } else {
                $variant = new Product($payload);
                $variant->sku = ($row['sku'] ?? null) ?: 'TMP-'.Str::uuid();
                $variant->save();
                if (str_starts_with($variant->sku, 'TMP-')) {
                    $variant->update(['sku' => $parent->sku.'-'.strtoupper(Str::substr(Str::slug($label, ''), 0, 6))]);
                }
            }
            $keep[] = $variant->id;
            $this->syncBarcodes($variant, array_filter([$row['barcode'] ?? null]));
        }

        $parent->variants()->whereNotIn('id', $keep)->update(['is_active' => false]);
    }

    /** Find a product (and unit) by any barcode, including scale barcodes. */
    public function findByBarcode(string $code): ?array
    {
        $code = trim($code);
        $barcode = ProductBarcode::query()->with(['product', 'productUnit.unit'])->where('barcode', $code)->first();
        if ($barcode?->product && $barcode->product->is_active) {
            return ['product' => $barcode->product, 'unit' => $barcode->productUnit, 'quantity' => null, 'price' => null];
        }

        $product = Product::query()->active()->sellable()->where('sku', $code)->first();
        if ($product) {
            return ['product' => $product, 'unit' => null, 'quantity' => null, 'price' => null];
        }

        if ($parsed = BarcodeParser::parseEmbedded($code)) {
            $match = ProductBarcode::query()->with('product')
                ->whereIn('barcode', [$parsed['lookup'], $parsed['code'], '2'.$parsed['code']])->first();
            if ($match?->product) {
                $product = $match->product;
                if ($parsed['type'] === 'weight') {
                    return ['product' => $product, 'unit' => null, 'quantity' => $parsed['weight'], 'price' => null];
                }
                $qty = Money::isZero($product->retail_price) ? '1' : Qty::div($parsed['price'], $product->retail_price, 3);

                return ['product' => $product, 'unit' => null, 'quantity' => $qty, 'price' => null, 'line_total' => $parsed['price']];
            }
        }

        return null;
    }
}
