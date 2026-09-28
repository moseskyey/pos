<?php

use App\Enums\MovementType;
use App\Exceptions\ApprovalRequiredException;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\BranchPrice;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Register;
use App\Models\User;
use App\Services\PromotionService;
use App\Services\SaleService;
use App\Services\SettingsService;
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
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->drinks = Category::create(['name' => 'Vinywaji']);
    $this->soda = Product::factory()->create(['name' => 'Soda 350ml', 'retail_price' => 1000, 'cost_price' => 500, 'category_id' => $this->drinks->id]);
    $this->rice = Product::factory()->create(['name' => 'Mchele 1kg', 'retail_price' => 3000, 'cost_price' => 2400]);
    foreach ([$this->soda, $this->rice] as $p) {
        app(StockService::class)->receive($this->branch->id, $p, 100, MovementType::Opening);
    }
    $this->promo = fn (array $data) => app(PromotionService::class)->save($data + ['name' => 'Offer', 'applies_to' => 'all', 'value' => 0, 'is_active' => true], $this->manager);
    $this->sell = fn (Product $p, int $qty, array $approvals = []) => app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $p->id, 'qty' => $qty]]],
        [['method' => 'cash', 'amount' => 1000000]], $this->cashier, $this->shift, (string) Str::uuid(), $approvals);
});

afterEach(fn () => Carbon::setTestNow());

it('works out each promotion type', function () {
    $service = app(PromotionService::class);
    $make = fn (array $a) => new Promotion($a + ['applies_to' => 'all', 'is_active' => true]);

    expect($service->discountFor($make(['type' => 'percent', 'value' => 10]), '3', '1000'))->toBe('300.00')
        ->and($service->discountFor($make(['type' => 'amount', 'value' => 150]), '4', '1000'))->toBe('600.00')
        ->and($service->discountFor($make(['type' => 'amount', 'value' => 5000]), '1', '1000'))->toBe('1000.00')
        ->and($service->discountFor($make(['type' => 'buy_get', 'buy_qty' => 2, 'get_qty' => 1]), '2', '1000'))->toBe('0.00')
        ->and($service->discountFor($make(['type' => 'buy_get', 'buy_qty' => 2, 'get_qty' => 1]), '7', '1000'))->toBe('2000.00')
        ->and($service->discountFor($make(['type' => 'multi_price', 'buy_qty' => 3, 'value' => 2500]), '7', '1000'))->toBe('1000.00')
        ->and($service->discountFor($make(['type' => 'multi_price', 'buy_qty' => 3, 'value' => 3500]), '3', '1000'))->toBe('0.00')
        ->and($service->discountFor($make(['type' => 'percent', 'value' => 50, 'min_qty' => 5]), '4', '1000'))->toBe('0.00');
});

it('respects dates, weekdays, happy hours, branches and targets', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 18:30')); // Monday
    $other = Branch::factory()->create(['code' => 'DSM02']);
    ($this->promo)(['type' => 'percent', 'value' => 10, 'applies_to' => 'categories', 'category_ids' => [$this->drinks->id],
        'days_of_week' => [1, 2], 'start_time' => '17:00', 'end_time' => '19:00', 'branch_ids' => [$this->branch->id]]);
    $service = fn () => new PromotionService;

    expect($service()->lineDiscount($this->soda, '2', '1000', $this->branch->id)['discount'])->toBe('200.00')
        ->and($service()->lineDiscount($this->rice, '2', '3000', $this->branch->id)['discount'])->toBe('0.00')
        ->and($service()->lineDiscount($this->soda, '2', '1000', $other->id)['discount'])->toBe('0.00');

    Carbon::setTestNow(Carbon::parse('2026-10-05 20:00'));
    expect($service()->lineDiscount($this->soda, '2', '1000', $this->branch->id)['discount'])->toBe('0.00');
    Carbon::setTestNow(Carbon::parse('2026-10-07 18:00')); // Wednesday
    expect($service()->lineDiscount($this->soda, '2', '1000', $this->branch->id)['discount'])->toBe('0.00');

    Carbon::setTestNow(Carbon::parse('2026-10-06 18:00'));
    Promotion::query()->update(['ends_on' => '2026-10-05']);
    expect($service()->lineDiscount($this->soda, '2', '1000', $this->branch->id)['discount'])->toBe('0.00');
});

it('picks the offer that saves the customer most', function () {
    ($this->promo)(['name' => 'Small', 'type' => 'percent', 'value' => 5]);
    $big = ($this->promo)(['name' => 'Big', 'type' => 'buy_get', 'buy_qty' => 1, 'get_qty' => 1]);

    $best = app(PromotionService::class)->lineDiscount($this->soda, '4', '1000', $this->branch->id);
    expect($best['discount'])->toBe('2000.00')->and($best['promotion']->is($big))->toBeTrue();
});

it('applies promotions at checkout without manager approval and records them', function () {
    app(SettingsService::class)->set(['pos.max_discount_percent' => 10]);
    ($this->promo)(['name' => 'Soda madness', 'type' => 'buy_get', 'buy_qty' => 1, 'get_qty' => 1, 'applies_to' => 'products', 'product_ids' => [$this->soda->id]]);

    $sale = ($this->sell)($this->soda, 4);  // 50% off, above the 10% cashier limit, but a promotion
    $item = $sale->items->first();

    expect($sale->total)->toEqual('2000.00')
        ->and($item->promo_discount)->toEqual('2000.00')
        ->and($item->discount_amount)->toEqual('0.00')
        ->and($item->promotion_name)->toContain('Soda madness')
        ->and($sale->discount_total)->toEqual('2000.00');

    $this->actingAs($this->cashier);
    $this->get(route('receipts.show', $sale))->assertOk()->assertSee('Soda madness');
});

it('still requires approval for manual discounts on top of a promotion', function () {
    app(SettingsService::class)->set(['pos.max_discount_percent' => 10]);
    ($this->promo)(['type' => 'percent', 'value' => 20]);

    expect(fn () => app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $this->rice->id, 'qty' => 1, 'discount_type' => 'percent', 'discount_value' => 15]]],
        [['method' => 'cash', 'amount' => 3000]], $this->cashier, $this->shift, (string) Str::uuid()))
        ->toThrow(ApprovalRequiredException::class);
});

it('lets a manager price override replace the promotion', function () {
    ($this->promo)(['type' => 'percent', 'value' => 50]);
    $sale = app(SaleService::class)->checkout(
        ['lines' => [['product_id' => $this->soda->id, 'qty' => 1, 'unit_price' => 900, 'price_override' => true]]],
        [['method' => 'cash', 'amount' => 900]], $this->cashier, $this->shift, (string) Str::uuid(), ['price_override' => $this->manager->id]);

    expect($sale->total)->toEqual('900.00')->and($sale->items->first()->promo_discount)->toEqual('0.00');
});

it('ignores promotions when the feature is off', function () {
    ($this->promo)(['type' => 'percent', 'value' => 50]);
    app(SettingsService::class)->set(['features.promotions' => false]);

    expect(($this->sell)($this->soda, 2)->total)->toEqual('2000.00');
});

it('shows promotions on the pos screen', function () {
    ($this->promo)(['name' => 'Two for one', 'type' => 'buy_get', 'buy_qty' => 1, 'get_qty' => 1]);
    $this->actingAs($this->cashier);

    Livewire::test(Terminal::class)
        ->call('addProduct', $this->soda->id)->call('addProduct', $this->soda->id)
        ->assertSee('Two for one')
        ->assertSee(__('Promotions'));
});

it('manages promotions through the pages', function () {
    $this->get('/promotions')->assertOk();
    $this->get('/promotions/create')->assertOk();
    $this->post('/promotions', [
        'name' => 'Weekend 3 for 2500', 'type' => 'multi_price', 'buy_qty' => 3, 'value' => 2500,
        'applies_to' => 'products', 'product_ids' => [$this->soda->id], 'days_of_week' => [6, 7], 'is_active' => 1,
    ])->assertRedirect('/promotions');
    $promotion = Promotion::firstWhere('name', 'Weekend 3 for 2500');
    expect($promotion->targets)->toHaveCount(1)->and($promotion->days_of_week)->toBe([6, 7]);

    $this->get("/promotions/{$promotion->id}/edit")->assertOk()->assertSee('Weekend 3 for 2500');
    $this->post("/promotions/{$promotion->id}/toggle")->assertRedirect();
    expect($promotion->fresh()->is_active)->toBeFalse();

    $this->post('/promotions', ['name' => 'Bad', 'type' => 'percent', 'value' => 150, 'applies_to' => 'all'])->assertSessionHasErrors('value');
    $this->post('/promotions', ['name' => 'Bad', 'type' => 'buy_get', 'applies_to' => 'products'])->assertSessionHasErrors(['buy_qty', 'get_qty', 'product_ids']);

    $this->actingAs($this->cashier);
    $this->get('/promotions')->assertForbidden();
    $this->delete("/promotions/{$promotion->id}")->assertForbidden();
});

it('sells at the branch price when branch prices are on', function () {
    BranchPrice::create(['branch_id' => $this->branch->id, 'product_id' => $this->rice->id, 'retail_price' => 3300]);

    expect(($this->sell)($this->rice, 1)->total)->toEqual('3000.00'); // feature off: normal price

    app(SettingsService::class)->set(['features.branch_prices' => true]);
    $sale = ($this->sell)($this->rice, 2);
    expect($sale->total)->toEqual('6600.00')->and($sale->items->first()->list_price)->toEqual('3300.00');
});

it('saves branch prices from the product page', function () {
    app(SettingsService::class)->set(['features.branch_prices' => true]);
    $this->get("/products/{$this->rice->id}")->assertOk()->assertSee(__('Branch prices'));

    $this->put("/products/{$this->rice->id}/branch-prices", ['prices' => [$this->branch->id => ['retail_price' => '3,200', 'wholesale_price' => '']]])
        ->assertRedirect("/products/{$this->rice->id}");
    expect(BranchPrice::first()->retail_price)->toEqual('3200.00')->and(BranchPrice::first()->wholesale_price)->toBeNull();

    $this->put("/products/{$this->rice->id}/branch-prices", ['prices' => [$this->branch->id => ['retail_price' => '']]]);
    expect(BranchPrice::count())->toBe(0);

    $other = Branch::factory()->create(['code' => 'DSM09']);
    $this->put("/products/{$this->rice->id}/branch-prices", ['prices' => [$other->id => ['retail_price' => 1]]])->assertSessionHasErrors('prices');

    $store = User::factory()->create();
    $store->assignRole('storekeeper');
    $store->branches()->attach($this->branch);
    $this->actingAs($store);
    $this->put("/products/{$this->rice->id}/branch-prices", ['prices' => [$this->branch->id => ['retail_price' => 1]]])->assertForbidden();
});
