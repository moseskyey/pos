<?php

namespace App\Services;

use App\Enums\TaxType;
use App\Imports\RowsImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * XLSX product import: parse → validate/preview → commit (create or update by SKU).
 */
class ProductImportService
{
    public const COLUMNS = [
        'name' => 'Name',
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'category' => 'Category',
        'subcategory' => 'Subcategory',
        'brand' => 'Brand',
        'unit' => 'Unit',
        'cost_price' => 'Cost price',
        'retail_price' => 'Retail price',
        'wholesale_price' => 'Wholesale price',
        'wholesale_min_qty' => 'Wholesale min qty',
        'tax_type' => 'Tax type',
        'reorder_level' => 'Reorder level',
        'track_stock' => 'Track stock',
        'track_batches' => 'Track batches',
        'is_active' => 'Active',
        'description' => 'Description',
    ];

    public function __construct(protected ProductService $products) {}

    /** @return array<int, array{row: int, data: array, errors: array, action: string}> */
    public function preview(string $path): array
    {
        $import = new RowsImport;
        Excel::import($import, $path);

        $seenSkus = [];
        $seenBarcodes = [];
        $result = [];
        foreach ($import->rows as $index => $raw) {
            $data = $this->normalize($raw->toArray());
            if (collect($data)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $existing = $data['sku'] ? Product::withTrashed()->where('sku', $data['sku'])->first() : null;
            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:160'],
                'sku' => ['nullable', 'string', 'max:64'],
                'barcode' => ['nullable', 'string', 'max:64'],
                'unit' => ['required', 'string'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'retail_price' => ['required', 'numeric', 'min:0'],
                'wholesale_price' => ['nullable', 'numeric', 'min:0'],
                'wholesale_min_qty' => ['nullable', 'numeric', 'min:0'],
                'tax_type' => ['required', 'in:standard,zero,exempt'],
                'reorder_level' => ['nullable', 'numeric', 'min:0'],
            ]);
            $errors = $validator->errors()->all();

            if ($data['sku'] && in_array($data['sku'], $seenSkus, true)) {
                $errors[] = __('Duplicate SKU in file.');
            }
            if ($data['barcode']) {
                if (in_array($data['barcode'], $seenBarcodes, true)) {
                    $errors[] = __('Duplicate barcode in file.');
                }
                $owner = ProductBarcode::where('barcode', $data['barcode'])->value('product_id');
                if ($owner && $owner !== $existing?->id) {
                    $errors[] = __('Barcode already used by another product.');
                }
            }
            if ($data['unit'] && ! $this->findUnit($data['unit'])) {
                $errors[] = __('Unknown unit ":unit".', ['unit' => $data['unit']]);
            }

            $seenSkus[] = $data['sku'];
            $seenBarcodes[] = $data['barcode'];
            $result[] = ['row' => $index + 2, 'data' => $data, 'errors' => $errors, 'action' => $existing ? 'update' : 'create'];
        }

        return $result;
    }

    /** Import valid rows. Returns [created, updated, skipped]. */
    public function commit(array $rows, User $user): array
    {
        $created = $updated = $skipped = 0;

        DB::transaction(function () use ($rows, $user, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                if ($row['errors']) {
                    $skipped++;

                    continue;
                }
                $d = $row['data'];
                $payload = [
                    'name' => $d['name'],
                    'sku' => $d['sku'],
                    'category_id' => $this->resolveCategory($d['category'], $d['subcategory']),
                    'brand_id' => $d['brand'] ? Brand::firstOrCreate(['name' => $d['brand']], ['is_active' => true])->id : null,
                    'unit_id' => $this->findUnit($d['unit'])->id,
                    'cost_price' => $d['cost_price'] ?? 0,
                    'retail_price' => $d['retail_price'],
                    'wholesale_price' => $d['wholesale_price'],
                    'wholesale_min_qty' => $d['wholesale_min_qty'],
                    'tax_type' => $d['tax_type'],
                    'reorder_level' => $d['reorder_level'] ?? 0,
                    'track_stock' => $d['track_stock'],
                    'track_batches' => $d['track_batches'],
                    'is_active' => $d['is_active'],
                    'description' => $d['description'],
                ];

                $existing = $d['sku'] ? Product::withTrashed()->where('sku', $d['sku'])->first() : null;
                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $barcodes = $existing->barcodes()->whereNull('product_unit_id')->pluck('barcode')->push($d['barcode'])->filter()->unique()->all();
                    $this->products->update($existing, $payload + ['barcodes' => $barcodes], $user, null, __('Excel import'));
                    $updated++;
                } else {
                    $this->products->create($payload + ['barcodes' => array_filter([$d['barcode']])], $user);
                    $created++;
                }
            }
        });

        activity('products')->withProperties(compact('created', 'updated', 'skipped'))->log('Products imported from Excel');

        return [$created, $updated, $skipped];
    }

    public function templateRows(): array
    {
        return [
            ['Azam Maji 500ml', 'BEV-00001', '6201234500012', 'Beverages', 'Water', 'Azam', 'pc', 350, 500, 450, 24, 'standard', 48, 'yes', 'no', 'yes', 'Bottled drinking water'],
            ['Unga wa Sembe 2kg', '', '', 'Groceries', 'Flour', 'Azam', 'pc', 3600, 4200, 4000, 10, 'exempt', 20, 'yes', 'no', 'yes', ''],
        ];
    }

    protected function normalize(array $raw): array
    {
        $get = fn ($key) => isset($raw[$key]) && $raw[$key] !== '' ? (is_string($raw[$key]) ? trim($raw[$key]) : $raw[$key]) : null;
        $bool = function ($value, bool $default) {
            if ($value === null) {
                return $default;
            }

            return in_array(strtolower((string) $value), ['1', 'yes', 'y', 'true', 'ndiyo'], true);
        };
        $num = fn ($v) => $v === null ? null : (is_numeric($v) ? $v : (is_numeric(str_replace(',', '', (string) $v)) ? str_replace(',', '', (string) $v) : $v));
        $tax = strtolower((string) ($get('tax_type') ?? 'standard'));
        $tax = match (true) {
            str_starts_with($tax, 'zero') => TaxType::Zero->value,
            str_starts_with($tax, 'ex') => TaxType::Exempt->value,
            default => $tax === '' ? 'standard' : ($tax === 'vat' || $tax === '18' ? 'standard' : $tax),
        };

        return [
            'name' => $get('name'),
            'sku' => $get('sku') !== null ? (string) $get('sku') : null,
            'barcode' => $get('barcode') !== null ? (string) (is_float($get('barcode')) ? number_format($get('barcode'), 0, '', '') : $get('barcode')) : null,
            'category' => $get('category'),
            'subcategory' => $get('subcategory'),
            'brand' => $get('brand'),
            'unit' => $get('unit') ?? 'pc',
            'cost_price' => $num($get('cost_price')),
            'retail_price' => $num($get('retail_price')),
            'wholesale_price' => $num($get('wholesale_price')),
            'wholesale_min_qty' => $num($get('wholesale_min_qty')),
            'tax_type' => $tax,
            'reorder_level' => $num($get('reorder_level')),
            'track_stock' => $bool($get('track_stock'), true),
            'track_batches' => $bool($get('track_batches'), false),
            'is_active' => $bool($get('active') ?? $get('is_active'), true),
            'description' => $get('description'),
        ];
    }

    protected function findUnit(string $value): ?Unit
    {
        return Unit::query()->where('short_name', $value)->orWhere('name', $value)->first();
    }

    protected function resolveCategory(?string $category, ?string $sub): ?int
    {
        if (! $category) {
            return null;
        }
        $parent = Category::firstOrCreate(['name' => $category, 'parent_id' => null], ['is_active' => true]);
        if (! $sub) {
            return $parent->id;
        }

        return Category::firstOrCreate(['name' => $sub, 'parent_id' => $parent->id], ['is_active' => true])->id;
    }

    /** Full export rows in the same layout as the import template. */
    public function exportRows(): array
    {
        return Product::query()->with(['category.parent', 'brand', 'unit', 'barcodes'])->sellable()->orderBy('name')->get()
            ->map(fn (Product $p) => [
                $p->name, $p->sku, $p->primaryBarcode(),
                $p->category?->parent?->name ?? $p->category?->name,
                $p->category?->parent ? $p->category->name : null,
                $p->brand?->name, $p->unit?->short_name,
                (float) $p->cost_price, (float) $p->retail_price,
                $p->wholesale_price !== null ? (float) $p->wholesale_price : null,
                $p->wholesale_min_qty !== null ? (float) $p->wholesale_min_qty : null,
                $p->tax_type?->value, (float) $p->reorder_level,
                $p->track_stock ? 'yes' : 'no', $p->track_batches ? 'yes' : 'no', $p->is_active ? 'yes' : 'no',
                $p->description,
            ])->all();
    }

    public static function headings(): array
    {
        return array_values(Arr::except(self::COLUMNS, []));
    }
}
