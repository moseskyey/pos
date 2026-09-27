<?php

use App\Livewire\Tables\CategoriesTable;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Livewire\Livewire;

it('renders catalog pages', function (string $url) {
    actingAsRole('owner');
    Product::factory()->count(3)->create();
    $this->get($url)->assertOk();
})->with(['/products', '/products/create', '/categories', '/brands', '/units', '/products/import', '/products/bulk-price', '/labels']);

it('renders product show and edit pages', function () {
    actingAsRole('owner');
    $product = Product::factory()->create();
    $this->get(route('products.show', $product))->assertOk()->assertSee($product->name);
    $this->get(route('products.edit', $product))->assertOk();
});

it('stores a product through the form request', function () {
    actingAsRole('owner');
    $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
    $this->post(route('products.store'), [
        'name' => 'Blueband 250g', 'unit_id' => $unit->id, 'retail_price' => '2,800', 'cost_price' => 2300,
        'tax_type' => 'standard', 'barcodes' => ['6200000000123', ''], 'track_stock' => 1, 'is_active' => 1,
    ])->assertRedirect();

    $product = Product::where('name', 'Blueband 250g')->first();
    expect($product->retail_price)->toEqual('2800.00')->and($product->barcodes)->toHaveCount(1);
});

it('rejects duplicate barcodes', function () {
    actingAsRole('owner');
    $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
    $existing = Product::factory()->create();
    $existing->barcodes()->create(['barcode' => '999']);
    $this->post(route('products.store'), ['name' => 'X', 'unit_id' => $unit->id, 'retail_price' => 1, 'tax_type' => 'standard', 'barcodes' => ['999']])
        ->assertSessionHasErrors('barcodes.0');
});

it('manages categories through the livewire modal', function () {
    actingAsRole('owner');
    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('form.name', 'Beverages')
        ->call('saveForm')
        ->assertHasNoErrors();
    expect(Category::where('name', 'Beverages')->exists())->toBeTrue();
});

it('prints barcode labels', function () {
    actingAsRole('owner');
    $product = Product::factory()->create();
    $this->post(route('labels.print'), ['size' => 'a4-30', 'items' => [['product_id' => $product->id, 'quantity' => 3]]])
        ->assertOk()->assertSee('<svg', false);
});

it('hides cost price from cashiers', function () {
    actingAsRole('cashier');
    $product = Product::factory()->create(['cost_price' => 12345]);
    $this->get(route('products.show', $product))->assertOk()->assertDontSee('12,345');
});
