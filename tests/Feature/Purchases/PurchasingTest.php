<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Purchases\DocumentForm;
use App\Mail\PurchaseOrderMail;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\RecurringExpense;
use App\Models\Register;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\ReorderService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\SupplierLedgerService;
use App\Services\SupplierPaymentService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->supplier = Supplier::create(['name' => 'Bakhresa', 'payment_terms_days' => 30]);
    $this->product = Product::factory()->create(['cost_price' => 1000, 'retail_price' => 1500, 'tax_type' => 'standard', 'reorder_level' => 10]);
    $this->purchases = app(PurchaseService::class);
    $this->stock = app(StockService::class);
});

it('receives a purchase order in parts and updates status, stock, bill and ledger', function () {
    $order = $this->purchases->saveOrder($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 20, 'unit_cost' => 1000]], $this->user);
    expect($order->number)->toStartWith('PO-')->and($order->total)->toEqual('23600.00');

    $item = $order->items->first();
    $grn = $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 8, 'unit_cost' => 1000, 'purchase_order_item_id' => $item->id]], $this->user, [], $order);
    expect($order->fresh()->status)->toBe('partially_received')
        ->and($grn->number)->toStartWith('GRN-')
        ->and($this->stock->available($this->branch->id, $this->product->id))->toBe('8.000')
        ->and($this->supplier->fresh()->balance)->toEqual('9440.00')
        ->and(SupplierBill::where('goods_receipt_id', $grn->id)->first()->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());

    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 12, 'unit_cost' => 1000, 'purchase_order_item_id' => $item->id]], $this->user, [], $order->fresh());
    expect($order->fresh()->status)->toBe('received');
});

it('updates cost with moving average', function () {
    $this->stock->receive($this->branch->id, $this->product, 10, MovementType::Opening); // 10 @ 1000
    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 1400]], $this->user);
    expect($this->product->fresh()->cost_price)->toEqual('1200.00')
        ->and($this->product->priceHistories()->where('field', 'cost_price')->exists())->toBeTrue();
});

it('uses last cost when configured', function () {
    setting()->set('inventory.costing', 'last');
    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1750]], $this->user);
    expect($this->product->fresh()->cost_price)->toEqual('1750.00');
});

it('records batches and expiry on GRN', function () {
    $product = Product::factory()->batched()->create();
    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $product->id, 'quantity' => 6, 'unit_cost' => 500, 'batch_no' => 'LOT1', 'expiry_date' => now()->addMonths(6)->toDateString()]], $this->user);
    expect(ProductBatch::where('product_id', $product->id)->first())->batch_no->toBe('LOT1');
});

it('pays supplier bills FIFO and settles them', function () {
    $g1 = $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 1000]], $this->user);
    $g2 = $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000]], $this->user);
    $payment = app(SupplierPaymentService::class)->pay($this->supplier, 15000, PaymentMethod::Bank, $this->user, $this->branch->id);

    expect($payment->allocations)->toHaveCount(2)
        ->and($g1->bill->fresh()->status)->toBe('paid')
        ->and($g2->bill->fresh()->status)->toBe('partial')
        ->and($this->supplier->fresh()->balance)->toEqual('2700.00');
});

it('returns goods to supplier reducing stock and balance', function () {
    $grn = $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 1000]], $this->user);
    $item = $grn->items->first();
    $return = $this->purchases->returnToSupplier($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 1000, 'goods_receipt_item_id' => $item->id]], 'Damaged', $this->user, $grn);

    expect($return->total)->toEqual('3540.00')
        ->and($this->stock->available($this->branch->id, $this->product->id))->toBe('7.000')
        ->and($this->supplier->fresh()->balance)->toEqual('8260.00');
    expect(fn () => $this->purchases->returnToSupplier($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 8, 'unit_cost' => 1000, 'goods_receipt_item_id' => $item->id]], 'Again', $this->user, $grn))
        ->toThrow(BusinessRuleException::class);
});

it('suggests reorders grouped by last supplier', function () {
    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000]], $this->user);
    $groups = app(ReorderService::class)->suggestions($this->branch->id);
    expect($groups->first()['supplier_id'])->toBe($this->supplier->id)
        ->and($groups->first()['items']->first()['suggested'])->toEqual(25.0);
});

it('deducts drawer expenses from expected shift cash', function () {
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till']);
    $shift = app(ShiftService::class)->open($register, $this->user, 50000);
    $category = ExpenseCategory::create(['name' => 'Transport']);
    $expense = app(ExpenseService::class)->record($this->branch->id, ['expense_category_id' => $category->id, 'amount' => 7000, 'payment_method' => 'cash', 'paid_from_drawer' => true], $this->user);

    expect($expense->number)->toStartWith('EXP-')->and($expense->shift_id)->toBe($shift->id)
        ->and(app(ShiftService::class)->expectedCash($shift))->toBe('43000.00');
});

it('posts due recurring expenses', function () {
    $category = ExpenseCategory::create(['name' => 'Rent']);
    RecurringExpense::create(['branch_id' => $this->branch->id, 'expense_category_id' => $category->id, 'description' => 'Rent', 'amount' => 900000,
        'payment_method' => 'bank', 'frequency' => 'monthly', 'next_run_date' => now()->subMonths(1)->toDateString(), 'created_by' => $this->user->id]);

    expect(app(ExpenseService::class)->runRecurring())->toBe(2)
        ->and(RecurringExpense::first()->next_run_date->isFuture())->toBeTrue();
});

it('renders purchasing and expense pages', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/suppliers', '/suppliers/create', '/purchase-orders', '/purchase-orders/create', '/goods-receipts', '/goods-receipts/create',
    '/supplier-bills', '/supplier-payments/create', '/purchase-returns', '/purchase-returns/create', '/reorder', '/expenses', '/expenses/create', '/expenses/recurring']);

it('renders purchasing documents', function () {
    $order = $this->purchases->saveOrder($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 1000]], $this->user);
    $grn = $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 1000]], $this->user);
    $this->get(route('purchase-orders.show', $order))->assertOk();
    $this->get(route('purchase-orders.pdf', $order))->assertOk();
    $this->get(route('goods-receipts.show', $grn))->assertOk();
    $this->get(route('supplier-bills.show', $grn->bill))->assertOk();
    $this->get(route('suppliers.show', $this->supplier))->assertOk();
    $this->get(route('supplier-payments.create', ['supplier' => $this->supplier->id]))->assertOk();
    $this->get(route('purchase-returns.create', ['receipt' => $grn->id]))->assertOk();
});

it('creates a GRN through the livewire form', function () {
    Livewire::test(DocumentForm::class, ['mode' => 'receipt'])
        ->set('supplierId', $this->supplier->id)
        ->call('addProduct', $this->product->id)
        ->set('items.0.quantity', 6)
        ->set('items.0.unit_cost', 1100)
        ->call('save')
        ->assertRedirect();
    expect($this->stock->available($this->branch->id, $this->product->id))->toBe('6.000');
});

it('prints a supplier statement', function () {
    $order = $this->purchases->saveOrder($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000]], $this->user);
    $this->purchases->receive($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000, 'purchase_order_item_id' => $order->items->first()->id]], $this->user, [], $order);

    $data = app(SupplierLedgerService::class)->statement($this->supplier->fresh(), now()->subMonth(), now());
    expect($data['closing'])->toBe('5900.00')->and($data['billed'])->toBe('5900.00')->and($data['entries'])->toHaveCount(1);

    $this->get(route('suppliers.statement', $this->supplier))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('suppliers.show', $this->supplier))->assertSee(route('suppliers.statement', $this->supplier));
});

it('emails a purchase order with the PDF attached and marks it sent', function () {
    Mail::fake();
    $order = $this->purchases->saveOrder($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000]], $this->user);

    $this->post(route('purchase-orders.email', $order), ['email' => 'orders@bakhresa.test', 'message' => 'Deliver Monday'])->assertRedirect();

    expect($order->fresh()->status)->toBe('sent');
    Mail::assertQueued(PurchaseOrderMail::class, function ($mail) use ($order) {
        return $mail->hasTo('orders@bakhresa.test') && $mail->order->is($order) && $mail->note === 'Deliver Monday';
    });

    $this->post(route('purchase-orders.email', $order), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
});

it('renders the purchase order email', function () {
    $order = $this->purchases->saveOrder($this->branch->id, $this->supplier, [['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000]], $this->user);
    $mail = new PurchaseOrderMail($order->load(['supplier', 'branch']), 'Deliver Monday');
    $mail->assertSeeInHtml($order->number)->assertSeeInHtml('Deliver Monday');
    expect($mail->attachments())->toHaveCount(1)
        ->and($mail->attachments()[0]->as)->toBe($order->number.'.pdf');
});
