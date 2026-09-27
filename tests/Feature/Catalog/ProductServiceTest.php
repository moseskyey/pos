<?php

use App\Exports\ArrayExport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Unit;
use App\Services\BulkPriceService;
use App\Services\ProductImportService;
use App\Services\ProductService;
use Maatwebsite\Excel\Excel;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->pc = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
    $this->ctn = Unit::create(['name' => 'Carton', 'short_name' => 'ctn']);
    $this->service = app(ProductService::class);
});

it('creates a product with auto SKU, barcodes and carton unit', function () {
    $category = Category::create(['name' => 'Beverages']);
    $product = $this->service->create([
        'name' => 'Azam Maji 500ml', 'category_id' => $category->id, 'unit_id' => $this->pc->id,
        'cost_price' => 350, 'retail_price' => 500, 'tax_type' => 'standard',
        'barcodes' => ['6201234500012'],
        'units' => [['unit_id' => $this->ctn->id, 'factor' => 24, 'retail_price' => 10800, 'barcode' => '6211234500019']],
    ], $this->user);

    expect($product->sku)->toBe(sprintf('BEV-%05d', $product->id))
        ->and($product->barcodes)->toHaveCount(2)
        ->and($product->units->first()->factor)->toEqual('24.0000');

    $found = $this->service->findByBarcode('6211234500019');
    expect($found['product']->id)->toBe($product->id)->and($found['unit']->unit_id)->toBe($this->ctn->id);
});

it('logs price history when prices change', function () {
    $product = Product::factory()->create(['retail_price' => 500, 'cost_price' => 300]);
    $this->service->update($product, ['retail_price' => 600, 'name' => $product->name], $this->user, null, 'Supplier increase');

    $history = PriceHistory::where('product_id', $product->id)->get();
    expect($history)->toHaveCount(1)
        ->and($history->first()->field)->toBe('retail_price')
        ->and($history->first()->new_value)->toEqual('600.00')
        ->and($history->first()->reason)->toBe('Supplier increase');
});

it('ignores price changes from users without price permission', function () {
    $store = actingAsRole('storekeeper', Branch::first());
    $product = Product::factory()->create(['retail_price' => 500, 'cost_price' => 300]);
    $this->service->update($product, ['retail_price' => 1, 'cost_price' => 1, 'name' => 'Renamed'], $store);

    $product->refresh();
    expect($product->name)->toBe('Renamed')->and($product->retail_price)->toEqual('500.00')->and($product->cost_price)->toEqual('300.00');
});

it('creates variants as sellable child products', function () {
    $parent = $this->service->create([
        'name' => 'T-Shirt', 'unit_id' => $this->pc->id, 'retail_price' => 15000, 'cost_price' => 8000, 'tax_type' => 'standard',
        'has_variants' => true,
        'variants' => [
            ['attributes' => ['Size' => 'M', 'Colour' => 'Black'], 'retail_price' => 15000, 'barcode' => '1111'],
            ['attributes' => ['Size' => 'XL', 'Colour' => 'Black'], 'retail_price' => 16000],
        ],
    ], $this->user);

    expect($parent->variants)->toHaveCount(2)
        ->and($parent->variants->pluck('name')->all())->toContain('T-Shirt - M / Black')
        ->and(Product::sellable()->where('parent_id', $parent->id)->count())->toBe(2)
        ->and(Product::sellable()->whereKey($parent->id)->exists())->toBeFalse();
});

it('applies bulk price updates with rounding and history', function () {
    $cat = Category::create(['name' => 'Water']);
    $a = Product::factory()->create(['category_id' => $cat->id, 'retail_price' => 1000]);
    $b = Product::factory()->create(['category_id' => $cat->id, 'retail_price' => 2330]);
    $other = Product::factory()->create(['retail_price' => 1000]);

    $count = app(BulkPriceService::class)->apply(['category_id' => $cat->id, 'field' => 'retail_price', 'mode' => 'percent', 'value' => 10, 'rounding' => 50], $this->user);

    expect($count)->toBe(2)
        ->and($a->fresh()->retail_price)->toEqual('1100.00')
        ->and($b->fresh()->retail_price)->toEqual('2550.00')
        ->and($other->fresh()->retail_price)->toEqual('1000.00')
        ->and(PriceHistory::count())->toBe(2);
});

it('previews and imports products from xlsx', function () {
    $service = app(ProductImportService::class);
    $rows = [
        ['Azam Maji 500ml', 'BEV-1', '6201234500012', 'Beverages', 'Water', 'Azam', 'pc', 350, 500, 450, 24, 'standard', 48, 'yes', 'no', 'yes', ''],
        ['Bad row', '', '', '', '', '', 'boxes', 10, '', '', '', 'standard', '', '', '', '', ''],
    ];
    $path = storage_path('app/test-import.xlsx');
    file_put_contents($path, Maatwebsite\Excel\Facades\Excel::raw(new ArrayExport(ProductImportService::headings(), $rows), Excel::XLSX));

    $preview = $service->preview($path);
    expect($preview)->toHaveCount(2)->and($preview[0]['errors'])->toBe([])->and($preview[1]['errors'])->not->toBeEmpty();

    [$created, $updated, $skipped] = $service->commit($preview, $this->user);
    expect([$created, $updated, $skipped])->toBe([1, 0, 1]);
    $product = Product::where('sku', 'BEV-1')->first();
    expect($product->category->name)->toBe('Water')->and($product->category->parent->name)->toBe('Beverages')->and($product->brand->name)->toBe('Azam');
    @unlink($path);
});
