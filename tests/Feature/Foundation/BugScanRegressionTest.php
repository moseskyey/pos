<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\LabelController;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Register;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Reports\ReportRegistry;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\StockTakeService;
use App\Services\SupplierPaymentService;
use App\Support\BranchContext;

/* Regressions for the bug-scan report items #6, #8–#13. */
beforeEach(function () {
    $this->owner = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->product = Product::factory()->create(['cost_price' => 1000, 'retail_price' => 1500]);
    $this->supplier = Supplier::create(['name' => 'Bakhresa', 'payment_terms_days' => 30]);
});

it('asks for a branch instead of crashing or guessing in All-branches mode (#6, #13)', function () {
    Branch::factory()->create(['code' => 'DSM02']);
    session([BranchContext::SESSION_KEY => 'all']);

    $this->post(route('reorder.store'), ['supplier_id' => $this->supplier->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000, 'selected' => 1]]])
        ->assertRedirect()->assertSessionHas('error', __('Select a single branch in the navbar first.'));
    $this->post(route('supplier-bills.store'), ['supplier_id' => $this->supplier->id, 'bill_date' => now()->toDateString(), 'total' => 1000])
        ->assertSessionHas('error', __('Select a single branch in the navbar first.'));
    expect(PurchaseOrder::withoutGlobalScopes()->count())->toBe(0)
        ->and(SupplierBill::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses supplier payments from the drawer without an open shift, and counts them when there is one (#8)', function () {
    $payments = app(SupplierPaymentService::class);
    expect(fn () => $payments->pay($this->supplier, 1000, PaymentMethod::Cash, $this->owner, $this->branch->id, ['from_drawer' => true]))
        ->toThrow(BusinessRuleException::class);

    $shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'T']), $this->owner, 5000);
    $payments->pay($this->supplier, 2000, PaymentMethod::Cash, $this->owner, $this->branch->id, ['from_drawer' => true]);
    expect(app(ShiftService::class)->expectedCash($shift))->toBe('3000.00');
    expect(fn () => $payments->pay($this->supplier, 9000, PaymentMethod::Cash, $this->owner, $this->branch->id, ['from_drawer' => true]))
        ->toThrow(BusinessRuleException::class); // more than is in the drawer
});

it('survives malformed report dates and filters (#9)', function () {
    foreach (ReportRegistry::all()->keys() as $key) {
        $this->get(route('reports.show', $key).'?from=bad&to=nope')->assertOk();
        $this->get(route('reports.show', $key).'?from[]=x&preset[]=y&by[]=z')->assertOk();
    }
});

it('rejects odd branch-switch input without crashing (#10)', function () {
    $this->post(route('branch.switch'), ['branch_id' => ['x']])->assertSessionHasErrors('branch_id');
    $this->post(route('branch.switch'), ['branch_id' => 'all'])->assertRedirect()->assertSessionHasNoErrors();
});

it('rejects archived products on label printing (#11)', function () {
    $this->product->delete();
    $this->post(route('labels.print'), ['size' => array_key_first(LabelController::SIZES), 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('items.0.product_id');
});

it('only posts a stock take after it was submitted (#12)', function () {
    app(StockService::class)->receive($this->branch->id, $this->product, 10, MovementType::Opening);
    $takes = app(StockTakeService::class);
    $take = $takes->create($this->branch->id, $this->owner);
    expect(fn () => $takes->post($take, $this->owner))->toThrow(BusinessRuleException::class);
    $takes->submit($take, $this->owner);
    expect($takes->post($take->fresh(), $this->owner)->status)->toBe('posted');
});
