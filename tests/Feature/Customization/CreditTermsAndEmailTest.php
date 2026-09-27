<?php

use App\Enums\MovementType;
use App\Livewire\Pos\Terminal;
use App\Mail\SaleDocumentMail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\CustomerStatementService;
use App\Services\QuotationService;
use App\Services\SaleService;
use App\Services\SettingsService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = actingAsRole('manager');
    $this->branch = Branch::first();
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->product = Product::factory()->create(['retail_price' => 1000, 'cost_price' => 600]);
    app(StockService::class)->receive($this->branch->id, $this->product, 100, MovementType::Opening);
    $this->customer = Customer::create(['name' => 'Hoteli ya Amani', 'email' => 'amani@example.com', 'credit_limit' => 1000000]);
    $this->creditSale = fn (int $qty, ?Customer $customer = null) => app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $this->product->id, 'qty' => $qty]], 'customer_id' => ($customer ?? $this->customer)->id],
        [['method' => 'credit', 'amount' => $qty * 1000]], $this->cashier, $this->shift, (string) Str::uuid());
});

afterEach(fn () => Carbon::setTestNow());

it('sets due dates from the customer terms or the business default', function () {
    app(SettingsService::class)->set(['credit.default_days' => 14]);
    $sale = ($this->creditSale)(1);
    expect($sale->fresh()->due_date->toDateString())->toBe(today()->addDays(14)->toDateString());

    $this->customer->update(['credit_days' => 45]);
    $sale = ($this->creditSale)(1, $this->customer->fresh());
    expect($sale->fresh()->due_date->toDateString())->toBe(today()->addDays(45)->toDateString());

    $cash = app(SaleService::class)->checkout(['lines' => [['product_id' => $this->product->id, 'qty' => 1]]],
        [['method' => 'cash', 'amount' => 1000]], $this->cashier, $this->shift, (string) Str::uuid());
    expect($cash->due_date)->toBeNull();
});

it('falls back to 30 days when credit terms are switched off', function () {
    $this->customer->update(['credit_days' => 7]);
    app(SettingsService::class)->set(['features.credit_terms' => false, 'credit.default_days' => 14]);

    $sale = ($this->creditSale)(1, $this->customer->fresh());
    expect($sale->fresh()->due_date->toDateString())->toBe(today()->addDays(30)->toDateString());
});

it('ages debt by days past the due date', function () {
    $this->customer->update(['credit_days' => 10]);
    Carbon::setTestNow(now()->subDays(50));
    ($this->creditSale)(3, $this->customer->fresh());   // due 40 days ago → 31–60 overdue
    Carbon::setTestNow(now()->addDays(45));
    ($this->creditSale)(2, $this->customer->fresh());   // sold 5 days ago, due in 5 days → not yet due
    Carbon::setTestNow();

    $aging = app(CustomerStatementService::class)->aging($this->customer->fresh());
    expect($aging['current'])->toBe('2000.00')
        ->and($aging['1_30'])->toBe('0.00')
        ->and($aging['31_60'])->toBe('3000.00')
        ->and($aging['overdue'])->toBe('3000.00')
        ->and($aging['total'])->toBe('5000.00');

    $report = ReportRegistry::all()['debtors-aging']->run(ReportFilters::fromArray(['from' => now()->toDateString(), 'to' => now()->toDateString(), 'branch_ids' => [$this->branch->id]]));
    expect($report->totals['31_60'])->toBe('3000.00')->and($report->totals['current'])->toBe('2000.00');
});

it('lets only credit managers set payment terms', function () {
    $this->actingAs($this->manager);
    $this->put("/customers/{$this->customer->id}", ['name' => 'Hoteli ya Amani', 'type' => 'retail', 'credit_days' => 60])->assertRedirect();
    expect($this->customer->fresh()->credit_days)->toBe(60);

    $this->actingAs($this->cashier);
    $this->put("/customers/{$this->customer->id}", ['name' => 'Hoteli ya Amani', 'type' => 'retail', 'credit_days' => 365])->assertRedirect();
    expect($this->customer->fresh()->credit_days)->toBe(60);
});

it('emails an invoice with the pdf attached', function () {
    Mail::fake();
    $sale = ($this->creditSale)(2);
    $this->actingAs($this->manager);

    $this->post("/receipts/{$sale->id}/email", ['email' => 'amani@example.com', 'message' => 'Asante'])->assertRedirect()->assertSessionHas('success');

    Mail::assertQueued(SaleDocumentMail::class, function (SaleDocumentMail $mail) use ($sale) {
        $attachments = $mail->attachments();

        return $mail->hasTo('amani@example.com') && $mail->sale->is($sale) && ! $mail->isQuotation()
            && count($attachments) === 1 && $attachments[0]->as === $sale->number.'.pdf';
    });
    $this->post("/receipts/{$sale->id}/email", ['email' => 'not-an-email'])->assertSessionHasErrors('email');
});

it('emails quotations and respects the feature switch', function () {
    Mail::fake();
    $this->actingAs($this->manager);
    $quote = app(QuotationService::class)->save(['lines' => [['product_id' => $this->product->id, 'qty' => 5]], 'customer_id' => $this->customer->id], $this->manager, $this->branch->id, today()->addDays(14)->toDateString());

    $this->get("/quotations/{$quote->id}")->assertSee(__('Email quotation'));
    $this->post("/receipts/{$quote->id}/email", ['email' => 'amani@example.com'])->assertRedirect();
    Mail::assertQueued(SaleDocumentMail::class, fn (SaleDocumentMail $m) => $m->isQuotation() && str_contains($m->envelope()->subject, $quote->number));

    app(SettingsService::class)->set(['features.email_documents' => false]);
    $this->post("/receipts/{$quote->id}/email", ['email' => 'amani@example.com'])->assertNotFound();
    $this->get("/quotations/{$quote->id}")->assertDontSee(__('Email quotation'));
});

it('does not let users email sales from another branch', function () {
    Mail::fake();
    $sale = ($this->creditSale)(1);
    $other = Branch::factory()->create(['code' => 'DSM02', 'name' => 'Mbezi']);
    $outsider = User::factory()->create(['default_branch_id' => $other->id]);
    $outsider->assignRole('manager');
    $outsider->branches()->attach($other);
    $this->actingAs($outsider);

    $this->post("/receipts/{$sale->id}/email", ['email' => 'x@example.com'])->assertNotFound();
    Mail::assertNothingQueued();
});

it('emails the receipt from the pos success screen', function () {
    Mail::fake();
    $this->actingAs($this->cashier);

    Livewire::test(Terminal::class)
        ->call('addProduct', $this->product->id)
        ->call('selectCustomer', $this->customer->id)
        ->call('openPayment')->call('quickCash', 'exact')->call('checkout')
        ->assertSet('completed.email', 'amani@example.com')
        ->call('emailReceipt');

    Mail::assertQueued(SaleDocumentMail::class, fn ($m) => $m->hasTo('amani@example.com'));
});
