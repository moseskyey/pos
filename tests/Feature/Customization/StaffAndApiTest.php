<?php

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Pos\Terminal;
use App\Models\ApiToken;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\CommissionService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\SettingsService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = actingAsRole('manager');
    $this->branch = Branch::first();
    app(SettingsService::class)->set(['features.commission' => true, 'features.api' => true]);
    $this->cashier = User::factory()->create(['name' => 'Asha Cashier', 'commission_rate' => 2]);
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $this->attendant = User::factory()->create(['name' => 'Baraka Attendant', 'commission_rate' => 5]);
    $this->attendant->assignRole('cashier');
    $this->attendant->branches()->attach($this->branch);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->product = Product::factory()->create(['name' => 'Radio', 'retail_price' => 118000, 'cost_price' => 60000, 'tax_type' => 'standard']);
    app(StockService::class)->receive($this->branch->id, $this->product, 20, MovementType::Opening);
    $this->sell = fn (int $qty, ?int $salesperson = null) => app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $this->product->id, 'qty' => $qty]], 'salesperson_id' => $salesperson],
        [['method' => 'cash', 'amount' => 118000 * $qty]], $this->cashier, $this->shift, (string) Str::uuid());
});

it('credits sales to the chosen salesperson and works out commission less returns', function () {
    ($this->sell)(1);                              // cashier: 100,000 net of VAT
    $sale = ($this->sell)(2, $this->attendant->id); // attendant: 200,000 net
    app(ReturnService::class)->process($sale, [$sale->items->first()->id => ['quantity' => 1]], 'Changed mind', 'cash', $this->cashier, null, ['return' => $this->manager->id]);

    $rows = app(CommissionService::class)->summary(now()->startOfMonth(), now()->endOfDay(), [$this->branch->id])->keyBy('user.id');

    expect($sale->salesperson_id)->toBe($this->attendant->id)
        ->and($rows[$this->attendant->id]['net_sales'])->toBe('200000.00')
        ->and($rows[$this->attendant->id]['returns'])->toBe('100000.00')
        ->and($rows[$this->attendant->id]['commission'])->toBe('5000.00')
        ->and($rows[$this->cashier->id]['commission'])->toBe('2000.00');
});

it('refuses a salesperson from another branch', function () {
    $outsider = User::factory()->create();
    $outsider->branches()->attach(Branch::factory()->create(['code' => 'DSM07']));

    expect(fn () => ($this->sell)(1, $outsider->id))->toThrow(BusinessRuleException::class);
});

it('saves targets and rates from the targets page and shows progress', function () {
    ($this->sell)(1, $this->attendant->id);
    $month = now()->format('Y-m');

    $this->get('/targets')->assertOk()->assertSee('Baraka Attendant');
    $this->put('/targets', ['month' => $month, 'targets' => [$this->attendant->id => '400,000', 'branch' => '1000000'], 'rates' => [$this->attendant->id => '7.5']])
        ->assertRedirect("/targets?month=$month");

    expect(SalesTarget::where('user_id', $this->attendant->id)->value('amount'))->toEqual('400000.00')
        ->and(SalesTarget::whereNull('user_id')->value('amount'))->toEqual('1000000.00')
        ->and($this->attendant->fresh()->commission_rate)->toEqual('7.50');

    $row = app(CommissionService::class)->monthFor($this->attendant, [$this->branch->id]);
    expect($row['achieved'])->toBe(25.0)->and($row['commission'])->toBe('7500.00');

    $this->get(route('reports.show', 'commission'))->assertOk()->assertSee('Baraka Attendant');

    $this->actingAs($this->attendant);
    $this->get('/targets')->assertForbidden();
    $this->get('/dashboard')->assertOk()->assertSee(__('My sales this month'));
});

it('lets the cashier pick who made the sale at the pos', function () {
    $this->actingAs($this->cashier);
    Livewire::test(Terminal::class)
        ->assertSee(__('Sold by'))
        ->call('addProduct', $this->product->id)
        ->set('salespersonId', $this->attendant->id)
        ->call('openPayment')->call('quickCash', 'exact')->call('checkout');

    expect(Sale::latest('id')->first()->salesperson_id)->toBe($this->attendant->id);
});

it('shows camera scan buttons only when switched on', function () {
    $this->actingAs($this->cashier);
    Livewire::test(Terminal::class)->assertSeeHtml('data-camera-scan="#pos-search"');
    $this->get('/pos')->assertSeeHtml('id="cameraScanModal"');

    app(SettingsService::class)->set(['features.camera_scan' => false]);
    Livewire::test(Terminal::class)->assertDontSeeHtml('data-camera-scan');
});

// API ---------------------------------------------------------------------------

function apiToken(User $user, bool $write = false): string
{
    return app(ApiTokenService::class)->create($user, 'Test app', $write)[1];
}

it('creates and revokes api tokens from the profile page', function () {
    $owner = actingAsRole('owner', $this->branch);
    $this->post('/profile/api-tokens', ['name' => 'Online shop', 'write' => 1, 'expires_in_days' => 90])->assertSessionHas('api_token_plain');
    $plain = session('api_token_plain');
    $token = ApiToken::firstWhere('name', 'Online shop');

    expect($plain)->toMatch(ApiTokenService::PATTERN)
        ->and($token->token_hash)->toBe(hash('sha256', $plain))
        ->and($token->abilities)->toBe(['read', 'write']);
    $this->get('/profile')->assertSee($plain);                                 // shown once, right after creating it
    $this->get('/profile')->assertSee('Online shop')->assertDontSee($plain); // never again

    $this->delete("/profile/api-tokens/{$token->id}")->assertRedirect();
    expect(ApiToken::count())->toBe(0);

    $this->actingAs($this->cashier);
    $this->post('/profile/api-tokens', ['name' => 'x'])->assertForbidden();
});

it('authenticates api requests and rejects bad tokens', function () {
    $plain = apiToken($this->manager);

    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer dk_1_'.str_repeat('a', 40)])->assertUnauthorized();
    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer dk_999_'.substr($plain, -40)])->assertUnauthorized(); // other business id
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer $plain"])->assertOk()
        ->assertJsonPath('data.id', $this->manager->id)
        ->assertJsonPath('data.branches.0.id', $this->branch->id);

    ApiToken::query()->update(['expires_at' => now()->subDay()]);
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer $plain"])->assertUnauthorized();
});

it('serves products with stock and hides cost from users who cannot see it', function () {
    $headers = ['Authorization' => 'Bearer '.apiToken($this->manager)];
    $this->getJson('/api/v1/products?q=Radio', $headers)->assertOk()
        ->assertJsonPath('data.0.name', 'Radio')
        ->assertJsonPath('data.0.stock', '20.000')
        ->assertJsonPath('data.0.cost_price', '60000.00');

    $cashierHeaders = ['Authorization' => 'Bearer '.apiToken($this->cashier)];
    $this->getJson("/api/v1/products/{$this->product->id}", $cashierHeaders)->assertOk()->assertJsonMissingPath('data.cost_price');
});

it('scopes api data to the user and their branches', function () {
    $mine = ($this->sell)(1);
    $headers = ['Authorization' => 'Bearer '.apiToken($this->cashier)];
    $this->getJson('/api/v1/sales', $headers)->assertOk()->assertJsonPath('data.0.number', $mine->number);
    $this->getJson("/api/v1/sales/{$mine->id}", $headers)->assertOk()->assertJsonCount(1, 'data.items');

    $other = Branch::factory()->create(['code' => 'DSM05']);
    $this->getJson("/api/v1/products?branch_id={$other->id}", $headers)->assertForbidden();
    $this->getJson('/api/v1/reports/summary', $headers)->assertForbidden(); // cashiers cannot view reports

    $this->getJson('/api/v1/reports/summary', ['Authorization' => 'Bearer '.apiToken($this->manager)])->assertOk()
        ->assertJsonPath('data.transactions', 1)
        ->assertJsonPath('data.total', '118000.00')
        ->assertJsonPath('data.gross_profit', '40000.00');
});

it('only lets write tokens create customers, and stops when api access is off', function () {
    $read = ['Authorization' => 'Bearer '.apiToken($this->manager)];
    $write = ['Authorization' => 'Bearer '.apiToken($this->manager, true)];

    $this->postJson('/api/v1/customers', ['name' => 'Online Buyer', 'type' => 'retail'], $read)->assertForbidden();
    $this->postJson('/api/v1/customers', ['name' => 'Online Buyer', 'phone' => '0712 555 666', 'type' => 'retail'], $write)->assertCreated()
        ->assertJsonPath('data.phone', '255712555666');
    $this->postJson('/api/v1/customers', ['type' => 'retail'], $write)->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(Customer::where('name', 'Online Buyer')->exists())->toBeTrue();

    app(SettingsService::class)->set(['features.api' => false]);
    $this->getJson('/api/v1/me', $read)->assertForbidden();
});
