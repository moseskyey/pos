<?php

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\GiftCard;
use App\Models\Product;
use App\Models\Register;
use App\Models\Unit;
use App\Models\User;
use App\Services\GiftCardService;
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
    app(SettingsService::class)->set(['features.bundles' => true, 'features.gift_cards' => true]);
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
    $register = Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'Till 1']);
    $this->shift = app(ShiftService::class)->open($register, $this->cashier, 0);
    $this->stock = app(StockService::class);
    $this->sugar = Product::factory()->create(['name' => 'Sukari 1kg', 'retail_price' => 3000, 'cost_price' => 2500]);
    $this->oil = Product::factory()->create(['name' => 'Mafuta 1L', 'retail_price' => 6000, 'cost_price' => 5000]);
    foreach ([$this->sugar, $this->oil] as $p) {
        $this->stock->receive($this->branch->id, $p, 50, MovementType::Opening);
    }
    $this->checkout = fn (array $lines, array $payments, array $approvals = []) => app(SaleService::class)->checkout(
        ['lines' => $lines], $payments, $this->cashier, $this->shift, (string) Str::uuid(), $approvals);
});

function makeHamper(): Product
{
    test()->post('/products', [
        'name' => 'Hamper ya Sikukuu', 'unit_id' => Unit::firstOrCreate(['short_name' => 'pc'], ['name' => 'Piece'])->id, 'tax_type' => 'standard',
        'retail_price' => 20000, 'is_active' => 1, 'track_stock' => 1, 'is_bundle' => 1,
        'bundle_items' => [['component_id' => test()->sugar->id, 'quantity' => 2], ['component_id' => test()->oil->id, 'quantity' => 1]],
    ])->assertRedirect();

    return Product::firstWhere('name', 'Hamper ya Sikukuu');
}

it('creates a bundle whose cost comes from its items and which keeps no stock', function () {
    $hamper = makeHamper();

    expect($hamper->is_bundle)->toBeTrue()
        ->and($hamper->track_stock)->toBeFalse()
        ->and($hamper->bundleItems)->toHaveCount(2)
        ->and($hamper->cost_price)->toEqual('10000.00');
    $this->get("/products/{$hamper->id}")->assertOk()->assertSee(__('Bundle items'))->assertSee('Sukari 1kg');
    $this->get("/products/{$hamper->id}/edit")->assertOk()->assertSee(__('This product is a bundle of other items'));
});

it('rejects bundles of bundles or of themselves', function () {
    $hamper = makeHamper();
    $unit = Unit::first()->id;

    $this->post('/products', ['name' => 'Mega', 'unit_id' => $unit, 'tax_type' => 'standard', 'is_bundle' => 1,
        'bundle_items' => [['component_id' => $hamper->id, 'quantity' => 1]]])->assertSessionHasErrors('bundle_items');
    $this->put("/products/{$hamper->id}", ['name' => 'Hamper ya Sikukuu', 'unit_id' => $unit, 'tax_type' => 'standard', 'is_bundle' => 1,
        'bundle_items' => [['component_id' => $hamper->id, 'quantity' => 1]]])->assertSessionHasErrors('bundle_items');
    $this->post('/products', ['name' => 'Empty', 'unit_id' => $unit, 'tax_type' => 'standard', 'is_bundle' => 1])->assertSessionHasErrors('bundle_items');
});

it('sells a bundle from its items stock, and a void puts it back', function () {
    $hamper = makeHamper();
    $sale = ($this->checkout)([['product_id' => $hamper->id, 'qty' => 3]], [['method' => 'cash', 'amount' => 60000]]);
    $item = $sale->items->first();

    expect($this->stock->available($this->branch->id, $this->sugar->id))->toBe('44.000')
        ->and($this->stock->available($this->branch->id, $this->oil->id))->toBe('47.000')
        ->and($item->cost_price)->toEqual('10000.00')
        ->and($item->bundle_components)->toHaveCount(2);

    app(SaleService::class)->void($sale, $this->manager, 'Wrong item');
    expect($this->stock->available($this->branch->id, $this->sugar->id))->toBe('50.000')
        ->and($this->stock->available($this->branch->id, $this->oil->id))->toBe('50.000');
});

it('restocks a returned bundle as its items', function () {
    $hamper = makeHamper();
    $sale = ($this->checkout)([['product_id' => $hamper->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 40000]]);
    app(ReturnService::class)->process($sale, [$sale->items->first()->id => ['quantity' => 1, 'condition' => 'restock']], 'Unwanted', 'cash', $this->cashier, null, ['return' => $this->manager->id]);

    expect($this->stock->available($this->branch->id, $this->sugar->id))->toBe('48.000')
        ->and($this->stock->available($this->branch->id, $this->oil->id))->toBe('49.000');
});

it('blocks a bundle when an item is out of stock', function () {
    $hamper = makeHamper();
    expect(fn () => ($this->checkout)([['product_id' => $hamper->id, 'qty' => 60]], [['method' => 'cash', 'amount' => 1200000]]))
        ->toThrow(BusinessRuleException::class);
    expect($this->stock->available($this->branch->id, $this->sugar->id))->toBe('50.000');
});

it('sells a gift card for cash into the drawer, and a voucher needs no payment', function () {
    $this->actingAs($this->cashier);
    expect(fn () => app(GiftCardService::class)->issue(['value' => 10000, 'kind' => 'gift_card', 'payment_method' => 'cash'], $this->manager, $this->branch->id))
        ->toThrow(BusinessRuleException::class); // the manager has no open shift

    $card = app(GiftCardService::class)->issue(['value' => 10000, 'kind' => 'gift_card', 'payment_method' => 'cash'], $this->cashier, $this->branch->id);
    expect($card->balance)->toEqual('10000.00')->and(strlen($card->code))->toBe(12)
        ->and(CashMovement::withoutGlobalScopes()->where('shift_id', $this->shift->id)->where('type', 'in')->sum('amount'))->toEqual(10000);

    $voucher = app(GiftCardService::class)->issue(['value' => 5000, 'kind' => 'voucher'], $this->manager, $this->branch->id);
    expect($voucher->kind)->toBe('voucher')->and($voucher->transactions->first()->payment_method)->toBeNull();

    expect(fn () => app(GiftCardService::class)->issue(['value' => 5000, 'kind' => 'gift_card', 'payment_method' => 'mpesa'], $this->manager, $this->branch->id))
        ->toThrow(BusinessRuleException::class); // mobile money needs a reference
});

it('pays with a gift card, in parts, and restores it on void', function () {
    $card = app(GiftCardService::class)->issue(['value' => 10000, 'kind' => 'voucher'], $this->manager, $this->branch->id);
    $code = $card->displayCode();

    $sale = ($this->checkout)([['product_id' => $this->oil->id, 'qty' => 1]], [['method' => 'gift_card', 'amount' => 6000, 'reference' => strtolower($code)]]);
    expect($card->fresh()->balance)->toEqual('4000.00')
        ->and($sale->payments->first()->reference)->toBe($code);

    expect(fn () => ($this->checkout)([['product_id' => $this->oil->id, 'qty' => 1]], [['method' => 'gift_card', 'amount' => 6000, 'reference' => $code]]))
        ->toThrow(BusinessRuleException::class, '4,000');

    $split = ($this->checkout)([['product_id' => $this->oil->id, 'qty' => 1]], [['method' => 'gift_card', 'amount' => 4000, 'reference' => $code], ['method' => 'cash', 'amount' => 2000]]);
    expect($card->fresh()->balance)->toEqual('0.00')->and($split->payments)->toHaveCount(2);

    app(SaleService::class)->void($sale, $this->manager, 'Mistake');
    expect($card->fresh()->balance)->toEqual('6000.00')
        ->and($card->transactions()->pluck('type')->all())->toBe(['void', 'redeem', 'redeem', 'issue']);
});

it('refuses expired, deactivated or unknown cards', function () {
    $card = app(GiftCardService::class)->issue(['value' => 10000, 'kind' => 'voucher'], $this->manager, $this->branch->id);
    $pay = fn () => ($this->checkout)([['product_id' => $this->sugar->id, 'qty' => 1]], [['method' => 'gift_card', 'amount' => 3000, 'reference' => $card->code]]);

    $card->update(['expires_on' => today()->subDay()]);
    expect($pay)->toThrow(BusinessRuleException::class);
    $card->update(['expires_on' => null, 'is_active' => false]);
    expect($pay)->toThrow(BusinessRuleException::class);
    expect(fn () => ($this->checkout)([['product_id' => $this->sugar->id, 'qty' => 1]], [['method' => 'gift_card', 'amount' => 3000, 'reference' => 'NOPE-NOPE-NOPE']]))
        ->toThrow(BusinessRuleException::class);
});

it('checks a gift card at the pos and caps the payment at its balance', function () {
    $card = app(GiftCardService::class)->issue(['value' => 2000, 'kind' => 'voucher'], $this->manager, $this->branch->id);
    $this->actingAs($this->cashier);

    Livewire::test(Terminal::class)
        ->call('addProduct', $this->oil->id)
        ->call('openPayment')->call('addPayment', 'gift_card')
        ->set('payments.0.reference', strtolower($card->code))
        ->call('checkGiftCard', 0)
        ->assertSet('payments.0.amount', 2000.0)
        ->assertSet('payments.0.reference', $card->displayCode());
});

it('turns the gift card method off with the feature and keeps it away from expenses', function () {
    expect(collect(PaymentMethod::enabled())->contains(PaymentMethod::GiftCard))->toBeTrue();
    app(SettingsService::class)->set(['features.gift_cards' => false]);
    expect(collect(PaymentMethod::enabled())->contains(PaymentMethod::GiftCard))->toBeFalse();
    $this->get('/gift-cards')->assertNotFound();

    expect(PaymentMethod::GiftCard->isAccount())->toBeTrue()->and(PaymentMethod::Cash->isAccount())->toBeFalse();
});

it('manages gift cards through the pages', function () {
    $this->get('/gift-cards')->assertOk();
    $this->get('/gift-cards/create')->assertOk();
    $this->post('/gift-cards', ['kind' => 'voucher', 'value' => 7500, 'note' => 'Winner'])->assertRedirect();
    $card = GiftCard::firstWhere('note', 'Winner');

    $this->get("/gift-cards/{$card->id}")->assertOk()->assertSee($card->displayCode());
    $this->get("/gift-cards/{$card->id}/print")->assertOk()->assertSee($card->displayCode());
    $this->post("/gift-cards/{$card->id}/toggle")->assertRedirect();
    expect($card->fresh()->is_active)->toBeFalse();

    $this->post('/gift-cards', ['kind' => 'gift_card', 'value' => 100])->assertSessionHasErrors('payment_method');

    $this->actingAs($this->cashier);
    $this->get('/gift-cards')->assertForbidden();
});

it('creates a product with blank cost and price fields instead of failing', function () {
    $this->post('/products', ['name' => 'Blank prices', 'unit_id' => Unit::firstOrCreate(['short_name' => 'pc'], ['name' => 'Piece'])->id,
        'tax_type' => 'standard', 'cost_price' => '', 'retail_price' => '', 'is_active' => 1])->assertRedirect();

    $product = Product::firstWhere('name', 'Blank prices');
    expect($product->cost_price)->toEqual('0.00')->and($product->retail_price)->toEqual('0.00');
});
