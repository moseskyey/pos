<?php

use App\Services\Pos\CartCalculator;

it('computes inclusive VAT totals with line and cart discounts', function () {
    $calc = new CartCalculator(pricesIncludeVat: true);
    $result = $calc->calculate([
        'a' => ['qty' => 2, 'unit_price' => 1180, 'tax_rate' => 18],
        'b' => ['qty' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'discount_type' => 'fixed', 'discount_value' => 100],
    ], 'percent', 10);

    expect($result['subtotal'])->toBe('3360.00')
        ->and($result['line_discounts'])->toBe('100.00')
        ->and($result['cart_discount'])->toBe('326.00')
        ->and($result['total'])->toBe('2934.00')
        ->and($result['lines']['a']['cart_discount_share'])->toBe('236.00')
        ->and($result['lines']['b']['cart_discount_share'])->toBe('90.00')
        ->and($result['lines']['a']['tax_amount'])->toBe('324.00')
        ->and($result['tax_total'])->toBe('324.00');
});

it('adds VAT on top when prices are exclusive', function () {
    $result = (new CartCalculator(pricesIncludeVat: false))->calculate([['qty' => 1, 'unit_price' => 1000, 'tax_rate' => 18]]);
    expect($result['tax_total'])->toBe('180.00')->and($result['total'])->toBe('1180.00');
});

it('rounds totals to the nearest 50', function () {
    $result = (new CartCalculator(true, 50))->calculate([['qty' => 3, 'unit_price' => 333, 'tax_rate' => 0]]);
    expect($result['total'])->toBe('1000.00')->and($result['rounding'])->toBe('1.00');
});

it('caps discounts at the line amount', function () {
    $result = (new CartCalculator)->calculate([['qty' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'discount_type' => 'fixed', 'discount_value' => 900]]);
    expect($result['total'])->toBe('0.00');
});
