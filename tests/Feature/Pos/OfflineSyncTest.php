<?php

use App\Enums\MovementType;
use App\Livewire\Tables\SalesTable;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = actingAsRole('cashier');
    $this->branch = Branch::first();
    $this->product = Product::factory()->create(['name' => 'Sukari 1kg', 'retail_price' => 3500, 'cost_price' => 2500]);
    $this->product->barcodes()->create(['barcode' => '6201234599991']);
    app(StockService::class)->receive($this->branch->id, $this->product, 10, MovementType::Opening);
    $this->shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']), $this->user, 0);
});

function offlineSale(array $overrides = []): array
{
    return array_replace_recursive([
        'client_id' => (string) Str::uuid(),
        'sold_at' => now()->subMinutes(30)->toIso8601String(),
        'shift_id' => test()->shift->id,
        'lines' => [['product_id' => test()->product->id, 'qty' => 2, 'unit_price' => 3500]],
        'payments' => [['method' => 'cash', 'amount' => 10000]],
    ], $overrides);
}

it('serves the offline till and its catalogue', function () {
    $this->get(route('pos.offline'))->assertOk()->assertSee('offlineTill', false);
    $this->getJson(route('pos.offline.ping'))->assertOk()->assertJsonStructure(['user_id', 'csrf']);

    $catalog = $this->getJson(route('pos.offline.catalog'))->assertOk()->json();
    $product = collect($catalog['products'])->firstWhere('id', $this->product->id);
    expect($catalog['shift_id'])->toBe($this->shift->id)
        ->and($product['barcodes'])->toBe(['6201234599991'])
        ->and($product['stock'])->toBe('10.000')
        ->and(collect($catalog['methods'])->pluck('value'))->toContain('cash')->not->toContain('credit');
    expect(file_exists(public_path('sw.js')))->toBeTrue();
});

it('records queued sales once, at the time they happened', function () {
    $this->shift->forceFill(['opened_at' => now()->subHours(2)])->save();
    $sale = offlineSale();
    $res = $this->postJson(route('pos.offline.sync'), ['sales' => [$sale]])->assertOk()->json('results.0');
    expect($res['status'])->toBe('synced')->and($res['number'])->toStartWith('INV-')->and($res['flags'])->toBe([]);

    $this->postJson(route('pos.offline.sync'), ['sales' => [$sale]])->assertOk()->assertJsonPath('results.0.number', $res['number']);
    $record = Sale::withoutGlobalScopes()->sole();
    expect($record->total)->toEqual('7000.00')
        ->and($record->change_due)->toEqual('3000.00')
        ->and($record->synced_at)->not->toBeNull()
        ->and($record->created_at->diffInMinutes(now()))->toBeGreaterThanOrEqual(29)
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('8.000');

    // A wrong device clock can't date a sale before the shift or in the future.
    $this->postJson(route('pos.offline.sync'), ['sales' => [offlineSale(['sold_at' => now()->addDays(3)->toIso8601String()])]])->assertOk();
    expect(Sale::withoutGlobalScopes()->latest('id')->first()->created_at->lte(now()))->toBeTrue();
});

it('records sales that broke a rule and flags them for review', function () {
    $this->product->update(['retail_price' => 4000]); // price rose after the catalogue was downloaded
    $res = $this->postJson(route('pos.offline.sync'), ['sales' => [
        offlineSale(['lines' => [['qty' => 12]], 'payments' => [['amount' => 42000]]]), // more than the 10 in stock
    ]])->assertOk()->json('results.0');

    expect($res['status'])->toBe('synced')->and($res['flags'])->toContain('price_override', 'negative_stock');
    $sale = Sale::withoutGlobalScopes()->sole();
    expect($sale->items->first()->unit_price)->toEqual('3500.00') // what the customer was charged
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('-2.000');
});

it('keeps short payments as balance due and syncs into a closed shift', function () {
    app(ShiftService::class)->close($this->shift, $this->user, 0);
    $res = $this->postJson(route('pos.offline.sync'), ['sales' => [offlineSale(['payments' => [['amount' => 5000]]])]])->assertOk()->json('results.0');

    expect($res['flags'])->toContain('short_paid', 'shift_closed');
    expect(Sale::withoutGlobalScopes()->sole()->balance_due)->toEqual('2000.00');
});

it('reports per-sale errors without blocking the rest', function () {
    $good = offlineSale();
    $bad = offlineSale(['lines' => [['product_id' => 999999]]]);
    $results = collect($this->postJson(route('pos.offline.sync'), ['sales' => [$bad, $good]])->assertOk()->json('results'))->keyBy('client_id');

    expect($results[$bad['client_id']]['status'])->toBe('error')
        ->and($results[$good['client_id']]['status'])->toBe('synced');
});

it('rejects payment methods that need the server', function () {
    $this->postJson(route('pos.offline.sync'), ['sales' => [offlineSale(['payments' => [['method' => 'credit']]])]])
        ->assertUnprocessable()->assertJsonValidationErrors('sales.0.payments.0.method');
});

it('shows offline sales and their review flags to managers', function () {
    $this->product->update(['retail_price' => 4000]);
    $this->postJson(route('pos.offline.sync'), ['sales' => [offlineSale(['payments' => [['amount' => 7000]]])]])->assertOk();
    $sale = Sale::withoutGlobalScopes()->sole();

    actingAsRole('manager', $this->branch);
    $this->get(route('sales.show', $sale))->assertOk()
        ->assertSee(__('Needs review:'))->assertSee(__('Price changed since the till went offline'));
    Livewire\Livewire::test(SalesTable::class)->set('filters.origin', 'review')->assertSee($sale->number);
});
