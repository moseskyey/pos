<?php

use App\Enums\MovementType;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\BarcodeParser;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Regression for the bug-scan report #1: a cashier could set any line total
 * from the browser (addProduct's 4th argument / the cart's
 * line_total_override) and skip the price-override PIN.
 */
beforeEach(function () {
    $this->cashier = actingAsRole('cashier');
    $this->branch = Branch::first();
    Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    setting()->set(['pos.below_cost' => 'allow']);
    $this->tv = Product::factory()->create(['name' => 'Hisense TV 32"', 'retail_price' => 23000, 'cost_price' => 15000]);
    $this->meat = Product::factory()->create(['name' => 'Nyama ya ng\'ombe', 'retail_price' => 12000, 'cost_price' => 8000, 'is_weighted' => true]);
    $this->meat->barcodes()->create(['barcode' => '2012345']); // weight labels, PLU 12345
    $this->meat->barcodes()->create(['barcode' => '2512345']); // price labels, PLU 12345
    foreach ([$this->tv, $this->meat] as $p) {
        app(StockService::class)->receive($this->branch->id, $p, 50, MovementType::Opening);
    }
});

function openTill()
{
    return Livewire::test(Terminal::class)->set('openingFloat', 0)->call('openShift');
}

it('ignores a line total passed to addProduct from the browser', function () {
    openTill()->call('addProduct', test()->tv->id, null, 1, 18899)
        ->call('openPayment')->call('quickCash', 'exact')->call('checkout');

    expect(Sale::sole()->total)->toEqual('23000.00');
});

it('never trusts prices edited in the live cart', function () {
    $pos = openTill()->call('addProduct', test()->tv->id);
    $key = array_key_first($pos->get('cart'));

    // Old field, new field and a plain price edit: none may lower the price without a PIN.
    $pos->set("cart.$key.line_total_override", 1)
        ->set("cart.$key.unit_price", 1)
        ->call('openPayment')->set('payments.0.amount', 23000)->call('checkout');
    expect(Sale::sole()->total)->toEqual('23000.00');

    $pos = openTill()->call('addProduct', test()->tv->id);
    $key = array_key_first($pos->get('cart'));
    $pos->set("cart.$key.unit_price", 18899)->set("cart.$key.price_override", true)
        ->call('openPayment')->set('payments.0.amount', 18899)->call('checkout')
        ->assertSet('approval.action', 'price_override'); // manager PIN required
    expect(Sale::count())->toBe(1);
});

it('rejects a forged scale barcode on a product that is not sold by label', function () {
    $pos = openTill()->call('addProduct', test()->tv->id);
    $key = array_key_first($pos->get('cart'));
    $forged = BarcodeParser::ean13('250000000100'); // "TSh 100" label for an unrelated PLU
    $pos->set("cart.$key.scale_barcode", $forged)->call('openPayment')->set('payments.0.amount', 23000)->call('checkout');

    expect(Sale::count())->toBe(0);
});

it('prices scale labels from the barcode, never below list price', function () {
    $weight = BarcodeParser::ean13('201234500750'); // 750 g of meat
    $label = BarcodeParser::ean13('251234506000'); // TSh 6,000 of meat at 12,000/kg
    openTill()->set('search', $weight)->call('scan')->set('search', $label)->call('scan')
        ->call('openPayment')->call('quickCash', 'exact')->call('checkout');
    $items = Sale::sole()->items->sortBy('quantity')->values();
    expect($items[0]->quantity)->toEqual('0.500')->and($items[0]->line_total)->toEqual('6000.00')
        ->and($items[1]->quantity)->toEqual('0.750')->and($items[1]->line_total)->toEqual('9000.00');

    // Direct service call: a client-supplied qty or total for a scale line is replaced by the barcode's.
    $shift = app(ShiftService::class)->current($this->cashier);
    $sale = app(SaleService::class)->checkout(['lines' => [[
        'product_id' => $this->meat->id, 'qty' => 5, 'scale_barcode' => $label, 'line_total_override' => 1,
    ]]], [['method' => 'cash', 'amount' => 6000]], $this->cashier, $shift, (string) Str::uuid());
    expect($sale->total)->toEqual('6000.00')->and($sale->items->first()->quantity)->toEqual('0.500');
});
