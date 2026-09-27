<?php

use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Models\Approval;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->manager = actingAsRole('manager');
    $this->branch = Branch::first();
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $this->manager->setPin('4321');
    $this->actingAs($this->cashier);

    $this->register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($this->register, $this->cashier, 50000);
    $this->product = Product::factory()->create(['retail_price' => 1000, 'cost_price' => 700, 'wholesale_price' => 900, 'wholesale_min_qty' => 10]);
    app(StockService::class)->receive($this->branch->id, $this->product, 100, MovementType::Opening);
    $this->sales = app(SaleService::class);
});

function cart(array $lines, array $extra = []): array
{
    return ['lines' => $lines] + $extra;
}

it('completes a cash sale with change and deducts stock', function () {
    $sale = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 3]]), [['method' => 'cash', 'amount' => 5000]], $this->cashier, $this->shift, (string) Str::uuid());

    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->total)->toEqual('3000.00')
        ->and($sale->change_due)->toEqual('2000.00')
        ->and($sale->number)->toStartWith('INV-DSM01-')
        ->and($sale->payments->first()->amount)->toEqual('3000.00')
        ->and($sale->items->first()->cost_price)->toEqual('700.00')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('97.000');
});

it('never trusts client prices and applies wholesale tiers', function () {
    $sale = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 1]]), [['method' => 'cash', 'amount' => 9000]], $this->cashier, $this->shift, (string) Str::uuid());
    expect($sale->items->first()->unit_price)->toEqual('900.00')->and($sale->items->first()->price_tier)->toBe('wholesale');
});

it('is idempotent for the same key', function () {
    $key = (string) Str::uuid();
    $a = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 1]]), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, $key);
    $b = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 1]]), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, $key);
    expect($a->id)->toBe($b->id)->and(Sale::count())->toBe(1)
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('99.000');
});

it('rejects short payments and requires mobile money references', function () {
    expect(fn () => $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 2]]), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class);
    expect(fn () => $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 1]]), [['method' => 'mpesa', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class);
});

it('splits payments across methods', function () {
    $sale = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 5]]),
        [['method' => 'mpesa', 'amount' => 3000, 'reference' => 'SGH7K2L9QX'], ['method' => 'cash', 'amount' => 3000]], $this->cashier, $this->shift, (string) Str::uuid());
    expect($sale->payments)->toHaveCount(2)->and($sale->change_due)->toEqual('1000.00');
});

it('requires manager approval for discounts above the limit', function () {
    $lines = [['product_id' => $this->product->id, 'qty' => 1, 'discount_type' => 'percent', 'discount_value' => 25]];
    expect(fn () => $this->sales->checkout(cart($lines), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid()))
        ->toThrow(ApprovalRequiredException::class);

    $sale = $this->sales->checkout(cart($lines), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid(), ['discount' => $this->manager->id, 'below_cost' => $this->manager->id]);
    expect($sale->total)->toEqual('750.00')->and(Approval::where('action', 'discount')->exists())->toBeTrue();
});

it('requires approval to sell below cost and for price overrides', function () {
    $lines = [['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 500, 'price_override' => true]];
    try {
        $this->sales->checkout(cart($lines), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid());
        $this->fail('Expected approval');
    } catch (ApprovalRequiredException $e) {
        expect($e->action)->toBe('price_override');
    }
    try {
        $this->sales->checkout(cart($lines), [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid(), ['price_override' => $this->manager->id]);
        $this->fail('Expected approval');
    } catch (ApprovalRequiredException $e) {
        expect($e->action)->toBe('below_cost');
    }
    $sale = $this->sales->checkout(cart($lines), [['method' => 'cash', 'amount' => 500]], $this->cashier, $this->shift, (string) Str::uuid(), ['price_override' => $this->manager->id, 'below_cost' => $this->manager->id]);
    expect($sale->total)->toEqual('500.00');
});

it('blocks selling beyond stock by default', function () {
    expect(fn () => $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 101]]), [['method' => 'cash', 'amount' => 200000]], $this->cashier, $this->shift, (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class);
    expect(Sale::count())->toBe(0);
});

it('records credit sales on the customer account within the limit', function () {
    $customer = Customer::create(['name' => 'Mama Neema', 'credit_limit' => 5000]);
    $sale = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 4]], ['customer_id' => $customer->id]),
        [['method' => 'credit', 'amount' => 4000]], $this->cashier, $this->shift, (string) Str::uuid());
    expect($customer->fresh()->balance)->toEqual('4000.00')->and($sale->ledger ?? null)->toBeNull();

    expect(fn () => $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 2]], ['customer_id' => $customer->id]),
        [['method' => 'credit', 'amount' => 2000]], $this->cashier, $this->shift, (string) Str::uuid()))->toThrow(ApprovalRequiredException::class);
});

it('voids a same-day sale and restores stock and credit', function () {
    $customer = Customer::create(['name' => 'Juma', 'credit_limit' => 10000]);
    $sale = $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 2]], ['customer_id' => $customer->id]),
        [['method' => 'credit', 'amount' => 2000]], $this->cashier, $this->shift, (string) Str::uuid());

    $this->sales->void($sale, $this->manager, 'Wrong items', $this->cashier);

    expect($sale->fresh()->status)->toBe(SaleStatus::Voided)
        ->and($customer->fresh()->balance)->toEqual('0.00')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('100.000');
});

it('holds and resumes a cart', function () {
    $held = $this->sales->hold(cart([['product_id' => $this->product->id, 'qty' => 2, 'unit_price' => 1000]]), $this->cashier, $this->shift, $this->branch->id, 'Mteja anarudi');
    expect($held->status)->toBe(SaleStatus::Held)->and($held->number)->toBeNull();
    $cart = $this->sales->resume($held, $this->cashier);
    expect($cart['lines'])->toHaveCount(1)->and(Sale::count())->toBe(0);
});

it('reconciles cash on shift close', function () {
    $this->sales->checkout(cart([['product_id' => $this->product->id, 'qty' => 3]]), [['method' => 'cash', 'amount' => 5000]], $this->cashier, $this->shift, (string) Str::uuid());
    $shifts = app(ShiftService::class);
    $shifts->cashMovement($this->shift, $this->cashier, 'out', 1000, 'Transport');

    expect($shifts->expectedCash($this->shift))->toBe('52000.00');
    $closed = $shifts->close($this->shift, $this->cashier, 51500, [10000 => 5, 1000 => 1, 500 => 1]);
    expect($closed->over_short)->toEqual('-500.00')->and($closed->status)->toBe('closed');
});
