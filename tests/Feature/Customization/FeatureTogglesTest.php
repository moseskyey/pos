<?php

use App\Enums\MovementType;
use App\Exports\ArrayExport;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductImportService;
use App\Services\SettingsService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Support\Features;
use App\Support\Navigation;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->owner = actingAsRole('owner');
});

it('keeps every existing module switched on by default, with loyalty opt-in', function () {
    foreach (array_keys(Features::all()) as $feature) {
        expect(Features::enabled($feature))->toBe($feature !== 'loyalty', "$feature default");
    }
});

it('saves module switches from the features settings page', function () {
    $this->get('/settings/features')->assertOk()->assertSee(__('Quick setup by business type'));

    $payload = collect(Features::all())->keys()
        ->mapWithKeys(fn ($f) => [str_replace('.', '_', Features::settingKey($f)) => in_array($f, ['quotations', 'loyalty'], true) ? '0' : '1'])->all();
    $this->put('/settings/features', $payload)->assertRedirect('/settings/features');

    expect(feature('quotations'))->toBeFalse()
        ->and(feature('loyalty'))->toBeFalse()
        ->and(setting('loyalty.enabled'))->toBeFalse()
        ->and(feature('transfers'))->toBeTrue();
});

it('hides switched-off modules from menus and returns 404 for their pages', function () {
    app(SettingsService::class)->set(['features.quotations' => false, 'features.transfers' => false, 'features.expenses' => false, 'features.stock_takes' => false, 'features.batches' => false]);

    foreach (['/quotations', '/quotations/create', '/transfers', '/transfers/create', '/expenses', '/expenses/recurring', '/stock-takes', '/stock/batches'] as $url) {
        $this->get($url)->assertNotFound();
    }
    $this->post('/stock-takes')->assertNotFound();

    $labels = collect(Navigation::visible())->flatMap(fn ($g) => collect($g['items'])->pluck('label'));
    expect($labels)->not->toContain(__('Quotations'), __('Transfers'), __('Expenses'), __('Stock Takes'), __('Batches & Expiry'))
        ->and($labels)->toContain(__('Sales'), __('Products'));

    $this->get('/reports/expiry')->assertForbidden();
    $this->get('/reports/expenses')->assertForbidden();
    $this->get('/reports/sales-summary')->assertOk();
});

it('applies a business-type preset', function () {
    $this->post('/settings/features/preset', ['preset' => 'pharmacy'])->assertRedirect('/settings/features');

    expect(setting('features.business_type'))->toBe('pharmacy')
        ->and(feature('batches'))->toBeTrue()
        ->and(feature('variants'))->toBeFalse()
        ->and(feature('scale_items'))->toBeFalse()
        ->and(feature('loyalty'))->toBeTrue();

    $this->post('/settings/features/preset', ['preset' => 'boutique']);
    expect(feature('variants'))->toBeTrue()->and(feature('batches'))->toBeFalse();

    $this->post('/settings/features/preset', ['preset' => 'nope'])->assertSessionHasErrors('preset');
});

it('only lets settings managers change features', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->branches()->attach(Branch::first());
    $this->actingAs($cashier);

    $this->post('/settings/features/preset', ['preset' => 'pharmacy'])->assertForbidden();
    $this->put('/settings/features', ['features_quotations' => '0'])->assertForbidden();
    expect(feature('quotations'))->toBeTrue();
});

it('hides variant, batch and scale fields on the product form when switched off', function () {
    $this->get('/products/create')->assertSee(__('This product has variants'))->assertSee(__('Track batches & expiry'));

    app(SettingsService::class)->set(['features.variants' => false, 'features.batches' => false, 'features.scale_items' => false]);

    $this->get('/products/create')->assertOk()
        ->assertDontSee(__('This product has variants'))
        ->assertDontSee(__('Track batches & expiry'))
        ->assertDontSee(__('Sold by weight (scale barcode)'));
});

it('blocks layaway checkout from the browser without permission or when switched off', function () {
    $branch = Branch::first();
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $cashier->branches()->attach($branch);
    $this->actingAs($cashier);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $branch->id, 'name' => 'Till 1']);
    app(ShiftService::class)->open($register, $cashier, 0);
    $product = Product::factory()->create(['retail_price' => 1000, 'cost_price' => 600]);
    app(StockService::class)->receive($branch->id, $product, 10, MovementType::Opening);
    $customer = Customer::create(['name' => 'Juma']);

    // Cashiers lack layaway.manage: calling checkout('layaway') directly must fail.
    Livewire::test(Terminal::class)
        ->call('addProduct', $product->id)->call('selectCustomer', $customer->id)
        ->call('checkout', 'layaway')->assertForbidden();

    Livewire::test(Terminal::class)->call('checkout', 'quotation')->assertStatus(400);

    $this->actingAs($this->owner);
    app(SettingsService::class)->set(['features.layaway' => false]);
    Livewire::test(Terminal::class)->call('checkoutLayaway')->assertForbidden();
});

it('downloads the rows that failed import validation', function () {
    $rows = [
        ['Good Soap', 'SOAP-1', '', '', '', '', 'pcs', 500, 800, '', '', 'standard', 5, 'yes', 'no', 'yes', ''],
        ['', 'BAD-1', '', '', '', '', 'pcs', 500, '', '', '', 'standard', 5, 'yes', 'no', 'yes', ''],
    ];
    $path = storage_path('app/test-import-errors.xlsx');
    file_put_contents($path, Maatwebsite\Excel\Facades\Excel::raw(new ArrayExport(ProductImportService::headings(), $rows), Excel::XLSX));
    Unit::firstOrCreate(['short_name' => 'pcs'], ['name' => 'Pieces']);

    $this->post('/products/import', ['file' => new UploadedFile($path, 'products.xlsx', null, null, true)])->assertRedirect('/products/import');
    $this->get('/products/import')->assertSee(__('Download :n rows with errors', ['n' => 1]));

    $response = $this->get('/products/import/errors')->assertOk();
    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
    expect($sheet)->toHaveCount(2)
        ->and($sheet[0])->toContain('Errors')
        ->and($sheet[1][1])->toBe('BAD-1')
        ->and(end($sheet[1]))->not->toBeEmpty();

    @unlink($path);
});
