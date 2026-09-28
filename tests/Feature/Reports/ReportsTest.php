<?php

use App\Livewire\Dashboard\Overview;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Register;
use App\Models\Supplier;
use App\Reports\Finance\ProfitLossReport;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\SettingsService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->product = Product::factory()->create(['retail_price' => 1180, 'cost_price' => 600, 'tax_type' => 'standard']);
    app(PurchaseService::class)->receive($this->branch->id, Supplier::create(['name' => 'Azam']), [['product_id' => $this->product->id, 'quantity' => 50, 'unit_cost' => 600]], $this->user);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till']);
    $shift = app(ShiftService::class)->open($register, $this->user, 0);
    app(SaleService::class)->checkout(['lines' => [['product_id' => $this->product->id, 'qty' => 10]]], [['method' => 'cash', 'amount' => 11800]], $this->user, $shift, (string) Str::uuid());
    app(ExpenseService::class)->record($this->branch->id, ['expense_category_id' => ExpenseCategory::create(['name' => 'Rent'])->id, 'amount' => 1000, 'payment_method' => 'bank'], $this->user);
});

it('renders every report', function (string $key) {
    app(SettingsService::class)->set(['features.commission' => true]); // opt-in report
    $this->get(route('reports.show', $key))->assertOk();
})->with(fn () => array_keys(ReportRegistry::all()->all()));

it('renders report variants', function () {
    foreach ([['sales-summary', ['group' => 'month']], ['sales-by-product', ['by' => 'category']], ['sales-by-product', ['by' => 'brand']],
        ['sales-by-cashier', ['by' => 'method']], ['sales-by-cashier', ['by' => 'branch']], ['gross-profit', ['by' => 'category']],
        ['low-stock', ['type' => 'dead', 'days' => 1]], ['low-stock', ['type' => 'out']], ['expiry', ['window' => 'expired']],
        ['stock-valuation', ['by' => 'product']], ['expenses', ['view' => 'detail']], ['sales-summary', ['preset' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->toDateString()]]] as [$key, $params]) {
        $this->get(route('reports.show', ['key' => $key] + $params))->assertOk();
    }
    $this->get('/reports')->assertOk()->assertSee(__('Profit & loss'));
});

it('computes profit and loss', function () {
    $f = new ReportFilters(now()->startOfDay(), now()->endOfDay(), [$this->branch->id]);
    $x = app(ProfitLossReport::class)->figures($f);
    expect($x['revenue'])->toBe('10000.00')   // 11,800 incl. 18% VAT
        ->and($x['cogs'])->toBe('6000.00')
        ->and($x['gross'])->toBe('4000.00')
        ->and($x['expenses'])->toBe('1000.00')
        ->and($x['net'])->toBe('3000.00');
});

it('computes the VAT position', function () {
    $result = ReportRegistry::find('vat')->run(new ReportFilters(now()->startOfDay(), now()->endOfDay(), [$this->branch->id]));
    $net = collect($result->rows)->last();
    // Output 1,800 − input 18% × 30,000 = 5,400 → refundable 3,600
    expect($net['tax'])->toBe('-3600.00');
});

it('queues exports and notifies the user with a download link', function () {
    Storage::fake('local');
    $this->post(route('reports.export', ['key' => 'sales-summary', 'format' => 'xlsx']))->assertRedirect();
    $this->post(route('reports.export', ['key' => 'profit-loss', 'format' => 'pdf']))->assertRedirect();
    expect($this->user->notifications()->count())->toBe(2)
        ->and(Storage::disk('local')->allFiles('exports/'.$this->user->id))->toHaveCount(2);
    $this->get($this->user->notifications()->first()->data['url'])->assertOk();
});

it('hides profit reports from users without permission', function () {
    actingAsRole('cashier', $this->branch);
    $this->get('/reports')->assertForbidden();
    $manager = actingAsRole('storekeeper', $this->branch);
    $this->get(route('reports.show', 'profit-loss'))->assertForbidden();
});

it('renders the dashboard with live filters', function () {
    $this->get('/dashboard')->assertOk()->assertSee(__('Sales — last 30 days'));
    Livewire::test(Overview::class)
        ->assertSee('TSh 11,800')
        ->set('preset', 'yesterday')
        ->assertSee(format_date(now()->subDay()))
        ->set('preset', 'month')
        ->assertSee('TSh 11,800');
});

it('sends daily stock and debt alerts', function () {
    $this->product->update(['reorder_level' => 100]);
    $this->artisan('dukapos:stock-alerts')->assertSuccessful();
    $this->artisan('dukapos:debt-alerts')->assertSuccessful();
    $this->artisan('dukapos:recurring-expenses')->assertSuccessful();
    expect($this->user->notifications()->count())->toBeGreaterThan(0);
});
