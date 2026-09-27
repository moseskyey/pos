<?php

use App\Enums\MovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Register;
use App\Services\EscPosReceiptService;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\EscPos\Printer;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = actingAsRole('cashier');
    $this->branch = Branch::first();
    setting()->set(['business.name' => 'Duka la Mama Neema', 'receipt.print_mode' => 'escpos', 'receipt.paper' => '80mm']);
    $this->product = Product::factory()->create(['name' => 'Unga wa Sembe 2kg – Azam', 'retail_price' => 5000, 'cost_price' => 3500]);
    app(StockService::class)->receive($this->branch->id, $this->product, 10, MovementType::Opening);
    $this->shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']), $this->user, 0);
    $this->sale = app(SaleService::class)->checkout(['lines' => [['product_id' => $this->product->id, 'qty' => 2]]],
        [['method' => 'cash', 'amount' => 20000]], $this->user, $this->shift, (string) Str::uuid());
});

it('renders an ESC/POS receipt that fits the paper and is plain ASCII', function () {
    $bytes = app(EscPosReceiptService::class)->render($this->sale);

    expect($bytes)->toStartWith("\x1B@")                          // initialise
        ->toContain('Duka la Mama Neema')
        ->toContain('Unga wa Sembe 2kg - Azam')                        // en dash transliterated
        ->toContain($this->sale->number)
        ->toContain("\x1DV\x42")                                       // cut
        ->not->toContain("\x1Bp");                                     // no drawer unless asked

    expect($bytes)->toContain("\x1D(k")->toContain($this->sale->verificationUrl() !== '' ? '/verify/' : '');
    // Printed text only: drop the QR payload block (GS ( k … print command).
    $printed = preg_replace('/\x1D\(k.*\x1D\(k\x03\x00\x31\x51\x30/s', '', $bytes);
    $text = preg_replace('/[\x00-\x1F\x7F-\xFF]+/', "\n", $printed);
    foreach (explode("\n", $text) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(48);
    }
    expect(Printer::forPaper('58mm')->width)->toBe(32);
});

it('prints the original with a drawer kick for the cashier, then logged copies', function () {
    $this->get(route('receipts.escpos', [$this->sale, 'drawer' => 1]))
        ->assertOk()->assertHeader('content-type', 'application/octet-stream')
        ->assertSee("\x1Bp\x00", false);
    expect($this->sale->fresh()->reprint_count)->toBe(0);

    $this->get(route('receipts.escpos', [$this->sale, 'drawer' => 1, 'drawer_only' => 1]))->assertOk()->assertContent("\x1B@\x1Bt\x00\x1Bp\x00\x19\xFA");

    $this->travel(20)->minutes();
    $this->get(route('receipts.escpos', [$this->sale, 'drawer' => 1]))->assertOk()->assertSee('COPY')->assertDontSee("\x1Bp", false);
    expect($this->sale->fresh()->reprint_count)->toBe(1);
});

it('logs a no-sale drawer opening', function () {
    $this->post(route('pos.drawer'))->assertOk()->assertContent("\x1B@\x1Bt\x00\x1Bp\x00\x19\xFA");
    expect(Activity::where('description', 'Cash drawer opened (no sale)')->count())->toBe(1);
});

it('shows printer controls on the POS in escpos mode', function () {
    $this->get('/pos')->assertOk()->assertSee(__('Connect printer'));
});
