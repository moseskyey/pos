<?php

use App\Enums\MovementType;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use App\Services\StockService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = actingAsRole('cashier');
    $this->branch = Branch::first();
    Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->product = Product::factory()->create(['name' => 'Azam Maji 500ml', 'retail_price' => 500, 'cost_price' => 350]);
    $this->product->barcodes()->create(['barcode' => '6201234500012']);
    app(StockService::class)->receive($this->branch->id, $this->product, 48, MovementType::Opening);
});

it('renders the pos page', function () {
    $this->get('/pos')->assertOk()->assertSee(__('Open your shift'));
});

it('opens a shift, scans a barcode and completes a sale', function () {
    $component = Livewire::test(Terminal::class)
        ->set('openingFloat', 20000)
        ->call('openShift')
        ->assertSet('shiftId', fn ($id) => $id !== null)
        ->set('search', '6201234500012')
        ->call('scan')
        ->call('scan')
        ->set('search', '6201234500012')
        ->call('scan');

    expect(count($component->get('cart')))->toBe(1)
        ->and(array_values($component->get('cart'))[0]['qty'])->toEqual(2);

    $component->call('openPayment')
        ->assertSet('modal', 'payment')
        ->call('quickCash', 'exact')
        ->call('checkout')
        ->assertSet('cart', [])
        ->assertSet('completed.total', '1000.00');

    expect(Sale::first()->total)->toEqual('1000.00')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('46.000');
});

it('asks for a manager pin for big discounts', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $manager->branches()->attach($this->branch);
    $manager->setPin('4321');
    $this->product->update(['cost_price' => 100]);

    Livewire::test(Terminal::class)
        ->set('openingFloat', 0)->call('openShift')
        ->call('addProduct', $this->product->id)
        ->set('cartDiscountType', 'percent')->set('cartDiscountValue', 50)
        ->call('openPayment')->call('quickCash', 'exact')
        ->call('checkout')
        ->assertSet('approval.action', 'discount')
        ->set('approvalPin', '0000')->call('submitApproval')
        ->assertHasErrors('pin')
        ->set('approvalPin', '4321')->call('submitApproval')
        ->assertSet('approval', null)
        ->assertSet('completed.total', '250.00');
});

it('holds and resumes sales', function () {
    Livewire::test(Terminal::class)
        ->set('openingFloat', 0)->call('openShift')
        ->call('addProduct', $this->product->id)
        ->set('holdNote', 'Mama Asha')
        ->call('hold')
        ->assertSet('cart', [])
        ->call('resume', Sale::where('status', 'held')->first()->id)
        ->assertCount('cart', 1);
});

it('prints receipts for the cashier', function () {
    $component = Livewire::test(Terminal::class)->set('openingFloat', 0)->call('openShift')
        ->call('addProduct', $this->product->id)->call('openPayment')->call('quickCash', 'exact')->call('checkout');
    $sale = Sale::first();
    $this->get(route('receipts.show', $sale))->assertOk()->assertSee($sale->number);
    $this->get(route('shifts.show', $sale->shift_id))->assertOk();
    $this->get(route('shifts.report', [$sale->shift_id, 'x']))->assertOk()->assertSee('X REPORT');
});
