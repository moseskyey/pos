<?php

use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Register;
use App\Models\User;
use Livewire\Livewire;

/*
 * Regression for the bug-scan report #7: a cashier raised a customer's credit
 * limit to 10,000,000 via PUT /customers/{id}, defeating the checkout limit.
 */
beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->customer = Customer::create(['name' => 'Mama Neema', 'type' => 'retail', 'credit_limit' => 50000]);
    $this->cashier = User::factory()->create(['default_branch_id' => $this->branch->id]);
    actingAsRole('owner', $this->branch);
    $this->cashier->assignRole('cashier');
    $this->cashier->branches()->attach($this->branch);
});

it('stops cashiers changing credit limits, wholesale status or opening debt', function () {
    $this->actingAs($this->cashier)->put(route('customers.update', $this->customer), [
        'name' => 'Mama Neema Juma', 'type' => 'wholesale', 'credit_limit' => 10000000,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $c = $this->customer->fresh();
    expect($c->name)->toBe('Mama Neema Juma')->and($c->credit_limit)->toEqual('50000.00')->and($c->type)->toBe('retail');

    $this->actingAs($this->cashier)->post(route('customers.store'), ['name' => 'New', 'type' => 'wholesale', 'credit_limit' => 999999, 'opening_balance' => 5000]);
    $new = Customer::where('name', 'New')->sole();
    expect($new->type)->toBe('retail')->and($new->credit_limit)->toEqual('0.00')->and($new->balance)->toEqual('0.00');
});

it('lets managers set credit terms', function () {
    $manager = User::factory()->create(['default_branch_id' => $this->branch->id]);
    $manager->assignRole('manager');
    $this->actingAs($manager)->put(route('customers.update', $this->customer), ['name' => 'Mama Neema', 'type' => 'wholesale', 'credit_limit' => 200000])->assertRedirect();
    expect($this->customer->fresh()->credit_limit)->toEqual('200000.00')->and($this->customer->fresh()->type)->toBe('wholesale');
});

it('does not let a cashier quick-add a wholesale customer at the till', function () {
    Register::withoutGlobalScopes()->create(['branch_id' => $this->branch->id, 'name' => 'T1']);
    $this->actingAs($this->cashier);
    Livewire::test(Terminal::class)->set('newCustomer', ['name' => 'Duka Jumla', 'phone' => '', 'type' => 'wholesale'])->call('quickAddCustomer');
    expect(Customer::where('name', 'Duka Jumla')->sole()->type)->toBe('retail');
});
