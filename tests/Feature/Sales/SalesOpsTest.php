<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Sales\SaleActions;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\CustomerStatementService;
use App\Services\LayawayService;
use App\Services\QuotationService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = actingAsRole('manager');
    $this->branch = Branch::first();
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $this->actingAs($this->cashier);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->product = Product::factory()->create(['retail_price' => 1000, 'cost_price' => 600]);
    app(StockService::class)->receive($this->branch->id, $this->product, 50, MovementType::Opening);
    $this->customer = Customer::create(['name' => 'Mama Neema', 'credit_limit' => 100000]);
    $this->sell = fn (int $qty, array $payments, ?int $customerId = null, string $status = 'completed') => app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $this->product->id, 'qty' => $qty]], 'customer_id' => $customerId],
        $payments, $this->cashier, $this->shift, (string) Str::uuid(), [], $status);
});

it('processes returns with restock and cash refunds, needing approval for cashiers', function () {
    $sale = ($this->sell)(4, [['method' => 'cash', 'amount' => 4000]]);
    $item = $sale->items->first();
    $service = app(ReturnService::class);

    expect(fn () => $service->process($sale, [$item->id => ['quantity' => 1]], 'Defective', 'cash', $this->cashier))
        ->toThrow(ApprovalRequiredException::class);

    $return = $service->process($sale, [$item->id => ['quantity' => 2, 'condition' => 'restock']], 'Defective', 'cash', $this->cashier, null, ['return' => $this->manager->id]);
    expect($return->refund_total)->toEqual('2000.00')
        ->and($return->number)->toStartWith('RET-')
        ->and($item->fresh()->returned_quantity)->toEqual('2.000')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('48.000')
        ->and(app(ShiftService::class)->expectedCash($this->shift))->toBe('2000.00');

    expect(fn () => $service->process($sale, [$item->id => ['quantity' => 3]], 'Again', 'cash', $this->manager))
        ->toThrow(BusinessRuleException::class);
});

it('does not restock damaged returns and can refund to store credit', function () {
    $sale = ($this->sell)(2, [['method' => 'cash', 'amount' => 2000]], $this->customer->id);
    $item = $sale->items->first();
    app(ReturnService::class)->process($sale, [$item->id => ['quantity' => 1, 'condition' => 'damaged']], 'Broken', 'store_credit', $this->manager);

    expect(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('48.000')
        ->and($this->customer->fresh()->store_credit)->toEqual('1000.00');
});

it('allocates customer payments to the oldest invoices first', function () {
    Carbon::setTestNow(now()->subDays(10));
    $old = ($this->sell)(3, [['method' => 'credit', 'amount' => 3000]], $this->customer->id);
    Carbon::setTestNow();
    $new = ($this->sell)(2, [['method' => 'credit', 'amount' => 2000]], $this->customer->id);
    expect($this->customer->fresh()->balance)->toEqual('5000.00');

    $payment = app(CustomerPaymentService::class)->receive($this->customer, 4000, PaymentMethod::Mpesa, $this->cashier, $this->branch->id, 'QWE123RTY');

    expect($payment->allocations)->toHaveCount(2)
        ->and($old->fresh()->balance_due)->toEqual('0.00')
        ->and($new->fresh()->balance_due)->toEqual('1000.00')
        ->and($this->customer->fresh()->balance)->toEqual('1000.00');

    $aging = app(CustomerStatementService::class)->aging($this->customer->fresh());
    expect($aging['current'])->toBe('1000.00')->and($aging['total'])->toBe('1000.00');
});

it('turns overpayments into store credit', function () {
    ($this->sell)(1, [['method' => 'credit', 'amount' => 1000]], $this->customer->id);
    app(CustomerPaymentService::class)->receive($this->customer, 1500, PaymentMethod::Cash, $this->cashier, $this->branch->id);
    $fresh = $this->customer->fresh();
    expect($fresh->balance)->toEqual('0.00')->and($fresh->store_credit)->toEqual('500.00');
});

it('saves quotations without touching stock and converts them', function () {
    $quote = app(QuotationService::class)->save(['lines' => [['product_id' => $this->product->id, 'qty' => 5]], 'customer_id' => $this->customer->id], $this->cashier, $this->branch->id, now()->addWeek()->toDateString());
    expect($quote->status)->toBe(SaleStatus::Quotation)->and($quote->number)->toStartWith('QT-')->and($quote->total)->toEqual('5000.00')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('50.000');

    $cart = app(QuotationService::class)->toCart($quote->load('items'));
    $sale = app(SaleService::class)->checkout($cart, [['method' => 'cash', 'amount' => 5000]], $this->cashier, $this->shift, (string) Str::uuid());
    app(QuotationService::class)->markConverted($quote, $sale);
    expect($quote->fresh()->status)->toBe(SaleStatus::Converted)->and($quote->fresh()->converted_sale_id)->toBe($sale->id);
});

it('reserves stock for layaways and completes them on full payment', function () {
    $sale = ($this->sell)(10, [['method' => 'cash', 'amount' => 3000]], $this->customer->id, 'layaway');
    expect($sale->status)->toBe(SaleStatus::Layaway)->and($sale->balance_due)->toEqual('7000.00')
        ->and(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('40.000');

    $sale = app(LayawayService::class)->addPayment($sale, 7000, PaymentMethod::Cash, $this->cashier);
    expect($sale->status)->toBe(SaleStatus::Completed)->and($sale->balance_due)->toEqual('0.00');
});

it('cancels layaways back to stock with deposits as store credit', function () {
    $sale = ($this->sell)(10, [['method' => 'cash', 'amount' => 3000]], $this->customer->id, 'layaway');
    app(LayawayService::class)->cancel($sale, $this->manager, 'Customer cancelled');
    expect(app(StockService::class)->available($this->branch->id, $this->product->id))->toBe('50.000')
        ->and($this->customer->fresh()->store_credit)->toEqual('3000.00');
});

it('renders sales and customer pages', function () {
    $this->actingAs($this->manager);
    $sale = ($this->sell)(2, [['method' => 'credit', 'amount' => 2000]], $this->customer->id);
    $this->actingAs($this->manager);
    foreach (['/sales', '/sales?status=layaway', '/returns', '/returns/create', '/quotations', '/quotations/create', '/customers', '/customers/create', '/customer-payments', '/customer-payments/create'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get(route('sales.show', $sale))->assertOk()->assertSee($sale->number);
    $this->get(route('customers.show', $this->customer))->assertOk()->assertSee('Mama Neema');
    $this->get(route('customers.edit', $this->customer))->assertOk();
    $this->get(route('customers.statement', $this->customer))->assertOk();
    $this->get(route('receipts.invoice', $sale))->assertOk();
    $this->get(route('receipts.delivery-note', $sale))->assertOk();
});

it('voids a sale from the sale page with a manager pin', function () {
    $sale = ($this->sell)(1, [['method' => 'cash', 'amount' => 1000]]);
    $this->manager->setPin('4321');
    Livewire::test(SaleActions::class, ['sale' => $sale])
        ->set('voidReason', 'Wrong item')
        ->call('void')
        ->assertSet('approval.action', 'void')
        ->set('approvalPin', '4321')
        ->call('submitApproval')
        ->assertRedirect(route('sales.show', $sale));
    expect($sale->fresh()->status)->toBe(SaleStatus::Voided);
});
