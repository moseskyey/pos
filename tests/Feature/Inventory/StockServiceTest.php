<?php

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Services\StockTakeService;
use App\Services\StockTransferService;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->stock = app(StockService::class);
});

it('writes a ledger entry and updates the cached balance', function () {
    $product = Product::factory()->create(['cost_price' => 300]);
    $this->stock->receive($this->branch->id, $product, 10, MovementType::Purchase);
    $this->stock->issue($this->branch->id, $product, 3, MovementType::Sale);

    expect($this->stock->available($this->branch->id, $product->id))->toBe('7.000')
        ->and(StockMovement::where('product_id', $product->id)->sum('quantity'))->toEqual(7)
        ->and(StockMovement::where('product_id', $product->id)->latest('id')->first()->balance_after)->toEqual('7.000');
});

it('blocks issuing more than available unless allowed', function () {
    $product = Product::factory()->create();
    $this->stock->receive($this->branch->id, $product, 2, MovementType::Opening);

    expect(fn () => $this->stock->issue($this->branch->id, $product, 5, MovementType::Sale))->toThrow(InsufficientStockException::class);
    $this->stock->issue($this->branch->id, $product, 5, MovementType::Sale, allowNegative: true);
    expect($this->stock->available($this->branch->id, $product->id))->toBe('-3.000');
});

it('ignores products that do not track stock', function () {
    $service = Product::factory()->service()->create();
    expect($this->stock->issue($this->branch->id, $service, 5, MovementType::Sale))->toBeEmpty()
        ->and(StockMovement::count())->toBe(0);
});

it('allocates batch-tracked stock first-expiry-first-out', function () {
    $product = Product::factory()->batched()->create();
    $this->stock->receive($this->branch->id, $product, 5, MovementType::Purchase, batchNo: 'LATE', expiryDate: now()->addYear()->toDateString());
    $this->stock->receive($this->branch->id, $product, 4, MovementType::Purchase, batchNo: 'SOON', expiryDate: now()->addMonth()->toDateString());

    $movements = $this->stock->issue($this->branch->id, $product, 6, MovementType::Sale);

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->batch->batch_no)->toBe('SOON')->and($movements[0]->quantity)->toEqual('-4.000')
        ->and($movements[1]->batch->batch_no)->toBe('LATE')->and($movements[1]->quantity)->toEqual('-2.000')
        ->and(ProductBatch::where('batch_no', 'LATE')->first()->quantity)->toEqual('3.000')
        ->and($this->stock->available($this->branch->id, $product->id))->toBe('3.000');
});

it('posts adjustments immediately for approvers and waits for others', function () {
    $product = Product::factory()->create();
    $service = app(StockAdjustmentService::class);

    $adj = $service->create($this->branch->id, AdjustmentReason::Opening, [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 50]], $this->user);
    expect($adj->status)->toBe('approved')->and($this->stock->available($this->branch->id, $product->id))->toBe('50.000');

    $store = actingAsRole('storekeeper', $this->branch);
    $pending = $service->create($this->branch->id, AdjustmentReason::Damaged, [['product_id' => $product->id, 'direction' => 'out', 'quantity' => 5]], $store);
    expect($pending->status)->toBe('pending')->and($this->stock->available($this->branch->id, $product->id))->toBe('50.000');

    $service->approve($pending, $this->user);
    expect($pending->fresh()->status)->toBe('approved')->and($this->stock->available($this->branch->id, $product->id))->toBe('45.000');
});

it('moves stock through the transfer workflow with discrepancies', function () {
    $mbezi = Branch::factory()->create();
    $product = Product::factory()->create(['cost_price' => 1000]);
    $this->stock->receive($this->branch->id, $product, 20, MovementType::Opening);
    $service = app(StockTransferService::class);

    $transfer = $service->request($this->branch->id, $mbezi->id, [['product_id' => $product->id, 'quantity' => 10]], $this->user);
    expect($transfer->status)->toBe('approved');

    $service->dispatch($transfer, $this->user);
    expect($this->stock->available($this->branch->id, $product->id))->toBe('10.000')
        ->and($this->stock->available($mbezi->id, $product->id))->toBe('0.000');

    $item = $transfer->items()->first();
    $service->receive($transfer->fresh(), $this->user, [$item->id => 9]);
    $transfer->refresh()->load('items');
    expect($transfer->status)->toBe('received')
        ->and($transfer->hasDiscrepancy())->toBeTrue()
        ->and($this->stock->available($mbezi->id, $product->id))->toBe('9.000');
});

it('posts stock take variances relative to the frozen snapshot', function () {
    $product = Product::factory()->create();
    $this->stock->receive($this->branch->id, $product, 20, MovementType::Opening);
    $service = app(StockTakeService::class);

    $take = $service->create($this->branch->id, $this->user);
    $this->stock->issue($this->branch->id, $product, 2, MovementType::Sale); // sale during count
    $service->count($take, $product->id, 17, $this->user); // counted 17 vs frozen 20 → -3
    $service->post($take, $this->user);

    expect($take->fresh()->status)->toBe('posted')
        ->and($this->stock->available($this->branch->id, $product->id))->toBe('15.000')
        ->and(StockMovement::where('type', 'stock_take')->sum('quantity'))->toEqual(-3);
});

it('scopes stock records to the users branches', function () {
    $other = Branch::factory()->create();
    $product = Product::factory()->create();
    $this->stock->receive($other->id, $product, 5, MovementType::Opening);
    $this->stock->receive($this->branch->id, $product, 7, MovementType::Opening);

    $cashier = actingAsRole('cashier', $this->branch);
    expect(ProductStock::count())->toBe(1)
        ->and(ProductStock::first()->quantity)->toEqual('7.000');
});
