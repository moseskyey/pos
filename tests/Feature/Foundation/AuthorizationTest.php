<?php

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Register;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Support\Str;

/*
 * Policies guard every model action (CLAUDE.md §3.7): role permissions plus
 * the user's branches. Form Requests authorise before validating.
 */
beforeEach(function () {
    $this->kariakoo = Branch::factory()->create(['code' => 'DSM01', 'name' => 'Kariakoo']);
    $this->mbezi = Branch::factory()->create(['code' => 'DSM02', 'name' => 'Mbezi']);
    $this->owner = actingAsRole('owner', $this->kariakoo);
    $this->product = Product::factory()->create(['retail_price' => 1000, 'cost_price' => 500]);
    app(StockService::class)->receive($this->mbezi->id, $this->product, 10, MovementType::Opening);
});

function userWithRole(string $role, Branch $branch): User
{
    $user = User::factory()->create(['default_branch_id' => $branch->id]);
    $user->assignRole($role);
    $user->branches()->attach($branch);

    return $user;
}

it('lets cashiers see only their own sales', function () {
    $alice = userWithRole('cashier', $this->mbezi);
    $bob = userWithRole('cashier', $this->mbezi);
    $shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->mbezi->id, 'name' => 'T1']), $alice, 0);
    $sale = app(SaleService::class)->checkout(['lines' => [['product_id' => $this->product->id, 'qty' => 1]]], [['method' => 'cash', 'amount' => 1000]], $alice, $shift, (string) Str::uuid());

    $this->actingAs($alice)->get(route('sales.show', $sale))->assertOk();
    $this->actingAs($bob)->get(route('sales.show', $sale))->assertForbidden();
    $this->actingAs($bob)->get(route('shifts.show', $shift))->assertForbidden();
    $this->actingAs($bob)->post(route('shifts.cash', $shift), ['type' => 'in', 'amount' => 100, 'reason' => 'x'])->assertForbidden();
});

it('keeps managers inside their branches', function () {
    $adjustment = app(StockAdjustmentService::class)->create($this->mbezi->id, AdjustmentReason::Damaged,
        [['product_id' => $this->product->id, 'direction' => 'out', 'quantity' => 1]], $this->owner);
    $kariakooManager = userWithRole('manager', $this->kariakoo);
    $mbeziManager = userWithRole('manager', $this->mbezi);

    $this->actingAs($mbeziManager)->get(route('adjustments.show', $adjustment))->assertOk();
    // Other branches' records are hidden by the branch scope (404) and refused by the policy (403).
    expect($this->actingAs($kariakooManager)->get(route('adjustments.show', $adjustment))->status())->toBeIn([403, 404]);
    expect($this->actingAs($kariakooManager)->post(route('adjustments.reject', $adjustment), ['rejection_reason' => 'no'])->status())->toBeIn([403, 404]);
    expect($adjustment->fresh()->status)->toBe($adjustment->status)->and($adjustment->fresh()->rejection_reason)->toBeNull();
});

it('limits storekeepers and cashiers to their jobs', function () {
    $store = userWithRole('storekeeper', $this->mbezi);
    $cashier = userWithRole('cashier', $this->mbezi);
    $order = app(PurchaseService::class)->saveOrder($this->mbezi->id, Supplier::create(['name' => 'Bakhresa']),
        [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 500]], $this->owner);

    $this->actingAs($store)->get(route('purchase-orders.show', $order))->assertOk();
    $this->actingAs($store)->post(route('purchase-orders.email', $order), ['email' => 'x@y.test'])->assertForbidden();
    $this->actingAs($cashier)->get(route('purchase-orders.show', $order))->assertForbidden();
    $this->actingAs($cashier)->get(route('suppliers.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
});

it('authorises form requests before validating them', function () {
    $cashier = userWithRole('cashier', $this->mbezi);
    // Invalid payload, but the answer is 403, not a validation error.
    $this->actingAs($cashier)->post(route('supplier-bills.store'), [])->assertForbidden();
    $this->actingAs($cashier)->post(route('products.bulk-price.store'), [])->assertForbidden();
});
