<?php

use App\Enums\AdjustmentReason;
use App\Livewire\Inventory\AdjustmentForm;
use App\Models\Branch;
use App\Models\Product;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Services\StockTakeService;
use App\Services\StockTransferService;
use Livewire\Livewire;

it('renders inventory pages', function (string $url) {
    actingAsRole('owner');
    $this->get($url)->assertOk();
})->with(['/stock', '/stock/movements', '/stock/batches', '/adjustments', '/adjustments/create', '/transfers', '/transfers/create', '/stock-takes']);

it('renders inventory documents', function () {
    $user = actingAsRole('owner');
    $branch = Branch::first();
    $other = Branch::factory()->create();
    $product = Product::factory()->create();
    $adj = app(StockAdjustmentService::class)->create($branch->id, AdjustmentReason::Opening, [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 10]], $user);
    $trf = app(StockTransferService::class)->request($branch->id, $other->id, [['product_id' => $product->id, 'quantity' => 2]], $user);
    $take = app(StockTakeService::class)->create($branch->id, $user);

    $this->get(route('adjustments.show', $adj))->assertOk()->assertSee($adj->number);
    $this->get(route('transfers.show', $trf))->assertOk()->assertSee($trf->number);
    $this->get(route('stock-takes.show', $take))->assertOk()->assertSee($take->number);
    $this->get(route('products.show', $product))->assertOk();
});

it('creates an adjustment through the livewire form', function () {
    actingAsRole('owner');
    $product = Product::factory()->create();
    $product->barcodes()->create(['barcode' => '6200000000017']);

    Livewire::test(AdjustmentForm::class)
        ->set('reason', 'found')
        ->set('productSearch', '6200000000017')
        ->call('pickFirst')
        ->assertCount('items', 1)
        ->set('items.0.quantity', 4)
        ->call('save')
        ->assertRedirect();

    expect(app(StockService::class)->available(Branch::first()->id, $product->id))->toBe('4.000');
});

it('lets storekeepers request but not approve adjustments', function () {
    actingAsRole('storekeeper');
    $this->get('/adjustments/create')->assertOk();
    $this->get('/stock-takes')->assertOk();
    $this->get('/settings')->assertForbidden();
});
