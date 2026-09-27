<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->user = actingAsRole('cashier', $this->branch);
    $this->user->forceFill(['pin' => Hash::make('1111')])->save();
});

it('locks the terminal and unlocks with the correct pin', function () {
    $this->postJson(route('lock'))->assertOk();
    expect(session('pos_locked'))->toBeTrue();

    $this->postJson(route('lock.verify'), ['pin' => '9999'])->assertStatus(422);
    expect(session('pos_locked'))->toBeTrue();

    $this->postJson(route('lock.verify'), ['pin' => '1111'])->assertOk()->assertJson(['ok' => true]);
    expect(session('pos_locked'))->toBeNull();
});

it('locks out after five wrong pins', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson(route('lock.verify'), ['pin' => '0000']);
    }
    expect($this->user->fresh()->pinLocked())->toBeTrue();
    $this->postJson(route('lock.verify'), ['pin' => '1111'])->assertStatus(423);
});
