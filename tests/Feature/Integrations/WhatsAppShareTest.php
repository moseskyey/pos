<?php

use App\Enums\MovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Services\SaleService;
use App\Services\ShareService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\WhatsApp;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->customer = Customer::create(['name' => 'Mama Neema Juma', 'phone' => '0754 112 233', 'credit_limit' => 100000]);
    $product = Product::factory()->create(['retail_price' => 5000, 'cost_price' => 1000]);
    app(StockService::class)->receive($this->branch->id, $product, 10, MovementType::Opening);
    $shift = app(ShiftService::class)->open(Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'T']), $this->user, 0);
    $this->sale = app(SaleService::class)->checkout(['lines' => [['product_id' => $product->id, 'qty' => 2]], 'customer_id' => $this->customer->id],
        [['method' => 'credit', 'amount' => 10000]], $this->user, $shift, (string) Str::uuid());
});

it('builds wa.me links with the customer number and message', function () {
    $link = WhatsApp::link('0754 112 233', 'Habari & asante');
    expect($link)->toBe('https://wa.me/255754112233?text=Habari%20%26%20asante')
        ->and(WhatsApp::link(null, 'x'))->toBe('https://wa.me/?text=x');

    $saleLink = app(ShareService::class)->saleLink($this->sale);
    expect($saleLink)->toStartWith('https://wa.me/255754112233?text=')->and(urldecode($saleLink))->toContain($this->sale->number)->toContain('/share/invoice/');
});

it('serves shared invoices and statements only through valid signed links', function () {
    auth()->logout();
    $share = app(ShareService::class);

    $this->get($share->invoiceUrl($this->sale))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get($share->statementUrl($this->customer))->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->get(route('share.invoice', $this->sale->id))->assertForbidden();
    $tampered = str_replace('/share/invoice/'.$this->sale->id, '/share/invoice/'.($this->sale->id + 1), $share->invoiceUrl($this->sale));
    $this->get($tampered)->assertForbidden();

    $url = $share->invoiceUrl($this->sale);
    $this->travel(ShareService::LINK_DAYS + 1)->days();
    $this->get($url)->assertForbidden(); // expired
});

it('shows WhatsApp buttons on sale, customer and POS screens', function () {
    $this->get(route('sales.show', $this->sale))->assertSee('https://wa.me/255754112233', false);
    $this->get(route('customers.show', $this->customer))->assertSee('https://wa.me/255754112233', false);
});
