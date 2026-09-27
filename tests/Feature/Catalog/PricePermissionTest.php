<?php

use App\Livewire\Inventory\AdjustmentForm;
use App\Livewire\Purchases\DocumentForm;
use App\Models\Branch;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use Livewire\Livewire;

/*
 * Regression for the bug-scan report #3 (storekeeper set variant prices and
 * costs) and #5 (cost prices in the page source for roles without cost access).
 */
beforeEach(function () {
    $this->owner = actingAsRole('owner');
    $this->branch = Branch::first();
    $this->pc = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);
    $this->parent = app(ProductService::class)->create([
        'name' => 'T-Shirt', 'unit_id' => $this->pc->id, 'retail_price' => 15000, 'cost_price' => 7777, 'tax_type' => 'standard',
        'has_variants' => true,
        'variants' => [['attributes' => ['Size' => 'M'], 'retail_price' => 15000, 'cost_price' => 7777]],
    ], $this->owner)->load('variants');
    $this->store = User::factory()->create(['default_branch_id' => $this->branch->id]);
    $this->store->assignRole('storekeeper');
    $this->store->branches()->attach($this->branch);
});

it('does not let a storekeeper set variant prices or costs', function () {
    $existing = $this->parent->variants->first();
    app(ProductService::class)->update($this->parent, [
        'name' => 'T-Shirt', 'unit_id' => $this->pc->id, 'tax_type' => 'standard', 'has_variants' => true,
        'variants' => [
            ['id' => $existing->id, 'attributes' => ['Size' => 'M'], 'retail_price' => 5, 'cost_price' => 1],
            ['attributes' => ['Size' => 'XL'], 'retail_price' => 5, 'cost_price' => 1],
        ],
    ], $this->store);

    $variants = $this->parent->fresh()->variants->keyBy(fn ($v) => $v->variant_attributes['Size']);
    expect($variants['M']->retail_price)->toEqual('15000.00')->and($variants['M']->cost_price)->toEqual('7777.00')
        ->and($variants['XL']->retail_price)->toEqual('15000.00')->and($variants['XL']->cost_price)->toEqual('7777.00');
});

it('keeps cost prices out of the page source for roles without cost access', function () {
    $this->actingAs($this->store);
    $this->get(route('products.edit', $this->parent))->assertOk()->assertDontSee('7777');
    Livewire::test(AdjustmentForm::class)->call('addProduct', $this->parent->variants->first()->id)
        ->assertSet('items.0.unit_cost', null)->assertDontSee('7777');
    Livewire::test(DocumentForm::class, ['mode' => 'receipt'])->call('addProduct', $this->parent->variants->first()->id)
        ->assertSet('items.0.unit_cost', 0.0);

    $this->actingAs($this->owner)->get(route('products.edit', $this->parent))->assertSee('7777');
});
