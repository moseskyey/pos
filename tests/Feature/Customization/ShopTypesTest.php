<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\ProductSupplier;
use App\Models\Register;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\ChequeService;
use App\Services\CustomerPaymentService;
use App\Services\PurchaseService;
use App\Services\ReorderService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\SerialService;
use App\Services\SettingsService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\SupplierPaymentService;
use App\Support\Money;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = actingAsRole('manager');
    $this->branch = Branch::first();
    app(SettingsService::class)->set(['features.serials' => true, 'features.pharmacy' => true, 'features.cheques' => true]);
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->phone = Product::factory()->create(['name' => 'Tecno Spark 20', 'retail_price' => 350000, 'cost_price' => 280000, 'track_serials' => true, 'warranty_months' => 12]);
    $this->amox = Product::factory()->create(['name' => 'Amoxil 500', 'generic_name' => 'Amoxicillin', 'strength' => '500mg', 'retail_price' => 5000, 'cost_price' => 3000, 'requires_prescription' => true]);
    $this->customer = Customer::create(['name' => 'Juma Electronics', 'credit_limit' => 5000000]);
    foreach ([$this->phone, $this->amox] as $p) {
        app(StockService::class)->receive($this->branch->id, $p, 10, MovementType::Opening);
    }
    $this->checkout = fn (array $lines, array $payments, array $extra = [], array $approvals = []) => app(SaleService::class)->checkout(
        ['lines' => $lines, 'customer_id' => $this->customer->id] + $extra, $payments, $this->cashier, $this->shift, (string) Str::uuid(), $approvals);
});

it('needs one serial per phone sold and records the warranty', function () {
    app(SerialService::class)->register($this->phone, $this->branch->id, ['356938035643809', '356938035643810'], $this->manager);

    expect(fn () => ($this->checkout)([['product_id' => $this->phone->id, 'qty' => 2, 'serials' => ['356938035643809']]], [['method' => 'cash', 'amount' => 700000]]))
        ->toThrow(BusinessRuleException::class);

    $sale = ($this->checkout)([['product_id' => $this->phone->id, 'qty' => 2, 'serials' => ['356938035643809', '356 938 035 643 810']]], [['method' => 'cash', 'amount' => 700000]]);
    $serial = ProductSerial::where('serial', '356938035643809')->first();

    expect($serial->status)->toBe('sold')
        ->and($serial->customer_id)->toBe($this->customer->id)
        ->and($serial->warranty_until->toDateString())->toBe(today()->addMonthsNoOverflow(12)->toDateString())
        ->and(app(SerialService::class)->find('356938035643810')->sale_id)->toBe($sale->id);

    expect(fn () => ($this->checkout)([['product_id' => $this->phone->id, 'qty' => 1, 'serials' => ['356938035643809']]], [['method' => 'cash', 'amount' => 350000]]))
        ->toThrow(BusinessRuleException::class, 'already');

    $this->actingAs($this->cashier);
    $this->get(route('receipts.show', $sale))->assertOk()->assertSee('356938035643809');
});

it('records unregistered serials at the till and puts serials back on void and return', function () {
    $sale = ($this->checkout)([['product_id' => $this->phone->id, 'qty' => 2, 'serials' => ['IMEI-A1', 'IMEI-B2']]], [['method' => 'cash', 'amount' => 700000]]);
    expect(ProductSerial::where('sale_id', $sale->id)->count())->toBe(2);

    // Returning 1 of 2 needs the serial named.
    $item = $sale->items->first();
    $return = fn ($serials) => app(ReturnService::class)->process($sale->fresh(), [$item->id => ['quantity' => 1, 'condition' => 'damaged', 'serials' => $serials]], 'Faulty', 'cash', $this->cashier, null, ['return' => $this->manager->id]);
    expect(fn () => $return(null))->toThrow(BusinessRuleException::class);
    $return(['IMEI-B2']);
    expect(ProductSerial::where('serial', 'IMEI-B2')->value('status'))->toBe('defective')
        ->and(ProductSerial::where('serial', 'IMEI-A1')->value('status'))->toBe('sold');

    $other = ($this->checkout)([['product_id' => $this->phone->id, 'qty' => 1, 'serials' => ['IMEI-C3']]], [['method' => 'cash', 'amount' => 350000]]);
    app(SaleService::class)->void($other, $this->manager, 'Wrong phone');
    expect(ProductSerial::where('serial', 'IMEI-C3')->value('status'))->toBe('in_stock');
});

it('scans an imei straight into the pos cart', function () {
    app(SerialService::class)->register($this->phone, $this->branch->id, ['861234567890123'], $this->manager);
    $this->actingAs($this->cashier);

    $pos = Livewire::test(Terminal::class)->set('search', '861234567890123')->call('scan');
    $line = collect($pos->get('cart'))->first();
    expect($line['product_id'])->toBe($this->phone->id)->and($line['serials'])->toBe(['861234567890123'])->and($line['qty'])->toEqual(1);

    $pos->call('addSerial', array_key_first($pos->get('cart')), '861234567890123');
    expect(collect($pos->get('cart'))->first()['serials'])->toHaveCount(1); // duplicate ignored
});

it('records serials on a goods received note', function () {
    $supplier = Supplier::create(['name' => 'Tecno TZ', 'payment_terms_days' => 30]);
    expect(fn () => app(PurchaseService::class)->receive($this->branch->id, $supplier, [['product_id' => $this->phone->id, 'quantity' => 2, 'unit_cost' => 280000, 'serials' => "SN1\n"]], $this->manager))
        ->toThrow(BusinessRuleException::class);

    app(PurchaseService::class)->receive($this->branch->id, $supplier, [['product_id' => $this->phone->id, 'quantity' => 2, 'unit_cost' => 275000, 'serials' => "SN1\nSN2"]], $this->manager);
    expect(ProductSerial::where('product_id', $this->phone->id)->where('status', 'in_stock')->pluck('serial')->sort()->values()->all())->toBe(['SN1', 'SN2'])
        ->and(ProductSupplier::where('product_id', $this->phone->id)->first()->last_cost)->toEqual('275000.00');
});

it('needs a prescription number for rx items', function () {
    expect(fn () => ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 5000]]))
        ->toThrow(BusinessRuleException::class, 'prescription');

    $sale = ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 5000]], ['prescription_ref' => 'RX-2231', 'prescriber' => 'Dr. Mwakyusa']);
    expect($sale->prescription_ref)->toBe('RX-2231')->and($sale->prescriber)->toBe('Dr. Mwakyusa');

    app(SettingsService::class)->set(['features.pharmacy' => false]);
    expect(($this->checkout)([['product_id' => $this->amox->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 5000]])->prescription_ref)->toBeNull();
});

it('finds medicines by generic name at the pos', function () {
    $this->actingAs($this->cashier);
    Livewire::test(Terminal::class)->set('search', 'amoxicil')->assertSee('Amoxil 500')->assertSee('Amoxicillin');
});

it('takes a post-dated cheque at the till and reverses it when it bounces', function () {
    $sale = ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 2]],
        [['method' => 'cheque', 'amount' => 10000, 'reference' => '004512', 'bank' => 'CRDB', 'cheque_date' => today()->addDays(10)->toDateString()]],
        ['prescription_ref' => 'RX-1']);
    $cheque = Cheque::first();
    expect($cheque->direction)->toBe('received')->and($cheque->isPostDated())->toBeTrue()->and($cheque->bank)->toBe('CRDB')->and($sale->balance_due)->toEqual('0.00');

    app(ChequeService::class)->bounce($cheque, $this->manager);
    expect($cheque->fresh()->status)->toBe('bounced')
        ->and($sale->fresh()->balance_due)->toEqual('10000.00')
        ->and($this->customer->fresh()->balance)->toEqual('10000.00');
    expect(fn () => app(ChequeService::class)->clear($cheque->fresh(), $this->manager))->toThrow(BusinessRuleException::class);

    expect(fn () => ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 1]], [['method' => 'cheque', 'amount' => 5000]], ['prescription_ref' => 'RX-2']))
        ->toThrow(BusinessRuleException::class); // cheque number required
});

it('reopens paid invoices when a customer payment cheque bounces', function () {
    $credit = ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 4]], [['method' => 'credit', 'amount' => 20000]], ['prescription_ref' => 'RX-3']);
    $payment = app(CustomerPaymentService::class)->receive($this->customer, 20000, PaymentMethod::Cheque, $this->manager, $this->branch->id, 'CHQ-77', null, null, ['bank' => 'NMB']);
    expect($credit->fresh()->balance_due)->toEqual('0.00')->and($this->customer->fresh()->balance)->toEqual('0.00');

    app(ChequeService::class)->bounce(Cheque::where('number', 'CHQ-77')->first(), $this->manager);
    expect($credit->fresh()->balance_due)->toEqual('20000.00')->and($this->customer->fresh()->balance)->toEqual('20000.00');
});

it('tracks cheques issued to suppliers', function () {
    $supplier = Supplier::create(['name' => 'Pharma Ltd', 'payment_terms_days' => 30]);
    app(PurchaseService::class)->receive($this->branch->id, $supplier, [['product_id' => $this->amox->id, 'quantity' => 10, 'unit_cost' => 3000]], $this->manager);
    $owed = $supplier->fresh()->balance;

    app(SupplierPaymentService::class)->pay($supplier, 30000, PaymentMethod::Cheque, $this->manager, $this->branch->id, [], 'OUT-9', null, today()->toDateString(), ['bank' => 'CRDB']);
    expect(Cheque::where('direction', 'issued')->count())->toBe(1)->and($supplier->fresh()->balance)->toEqual(Money::sub($owed, 30000));

    app(ChequeService::class)->bounce(Cheque::where('number', 'OUT-9')->first(), $this->manager);
    expect($supplier->fresh()->balance)->toEqual($owed)
        ->and(SupplierBill::where('supplier_id', $supplier->id)->first()->status)->not->toBe('paid');
});

it('manages cheques from the cheques page', function () {
    ($this->checkout)([['product_id' => $this->amox->id, 'qty' => 1]], [['method' => 'cheque', 'amount' => 5000, 'reference' => '1001']], ['prescription_ref' => 'RX']);
    $cheque = Cheque::first();

    $this->get('/cheques')->assertOk()->assertSee('1001');
    $this->put("/cheques/{$cheque->id}", ['status' => 'cleared'])->assertRedirect();
    expect($cheque->fresh()->status)->toBe('cleared');

    $this->actingAs($this->cashier);
    $this->get('/cheques')->assertForbidden();
    $this->put("/cheques/{$cheque->id}", ['status' => 'bounced'])->assertForbidden();
});

it('looks up warranty and records serials on the serials page', function () {
    $this->get('/serials')->assertOk();
    $this->post('/serials', ['product_id' => $this->phone->id, 'serials' => "AAA111\nBBB222"])->assertRedirect()->assertSessionHas('success');
    $this->post('/serials', ['product_id' => $this->phone->id, 'serials' => 'AAA111'])->assertSessionHas('error');
    $this->post('/serials', ['product_id' => $this->amox->id, 'serials' => 'X'])->assertSessionHasErrors('product_id');
    $this->get('/serials?q=aaa111')->assertOk()->assertSee('AAA111')->assertSee('Tecno Spark 20');

    $this->actingAs($this->cashier);
    $this->get('/serials?q=AAA111')->assertOk();
    $this->post('/serials', ['product_id' => $this->phone->id, 'serials' => 'CCC'])->assertForbidden();
});

it('links suppliers to products and prefers them for reordering', function () {
    $this->phone->update(['reorder_level' => 20]);
    $usual = Supplier::create(['name' => 'Usual', 'payment_terms_days' => 30]);
    $preferred = Supplier::create(['name' => 'Preferred', 'payment_terms_days' => 30]);
    app(PurchaseService::class)->receive($this->branch->id, $usual, [['product_id' => $this->phone->id, 'quantity' => 1, 'unit_cost' => 280000]], $this->manager);

    expect(app(ReorderService::class)->suggestions($this->branch->id)->first()['supplier'])->toBe('Usual');

    $this->post("/products/{$this->phone->id}/suppliers", ['supplier_id' => $preferred->id, 'supplier_sku' => 'TEC-S20', 'is_preferred' => 1])->assertRedirect();
    expect(app(ReorderService::class)->suggestions($this->branch->id)->first()['supplier'])->toBe('Preferred');
    $this->get("/products/{$this->phone->id}")->assertOk()->assertSee('TEC-S20');

    $link = ProductSupplier::where('supplier_id', $preferred->id)->first();
    $this->delete("/products/{$this->phone->id}/suppliers/{$link->id}")->assertRedirect();
    expect(ProductSupplier::where('supplier_id', $preferred->id)->exists())->toBeFalse();
});

it('saves pharmacy and serial fields from the product form', function () {
    $this->get('/products/create')->assertOk()->assertSee(__('Generic name'))->assertSee(__('Track serial / IMEI numbers'));
    $this->put("/products/{$this->amox->id}", ['name' => 'Amoxil 500', 'unit_id' => $this->amox->unit_id, 'tax_type' => 'exempt', 'generic_name' => 'Amoxicillin trihydrate',
        'strength' => '500mg', 'dosage_form' => 'capsule', 'requires_prescription' => 1, 'track_serials' => 0])->assertRedirect();
    expect($this->amox->fresh()->generic_name)->toBe('Amoxicillin trihydrate')->and($this->amox->fresh()->dosage_form)->toBe('capsule');

    app(SettingsService::class)->set(['features.serials' => false, 'features.pharmacy' => false]);
    $this->get('/products/create')->assertDontSee(__('Generic name'))->assertDontSee(__('Track serial / IMEI numbers'));
    $this->get('/serials')->assertNotFound();
    $this->get('/cheques')->assertOk();
});
