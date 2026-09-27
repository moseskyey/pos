<?php

use App\Support\BarcodeParser;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Qty;

it('does decimal-safe money math', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::mul('1999.99', 3))->toBe('5999.97')
        ->and(Money::sub(100, '33.333'))->toBe('66.67')
        ->and(Money::percent(12500, 18))->toBe('2250.00')
        ->and(Money::taxFromInclusive(1180, 18))->toBe('180.00')
        ->and(Money::roundToNearest('12,530', 50))->toBe('12550.00')
        ->and(Money::roundToNearest('12524', 50))->toBe('12500.00')
        ->and(Money::div(10, 3))->toBe('3.33')
        ->and(Money::div(10, 0))->toBe('0.00');
});

it('formats TZS amounts', function () {
    expect(Money::format(1234567))->toBe('TSh 1,234,567')
        ->and(Money::format('-500.4'))->toBe('TSh -500')
        ->and(Money::format(99, false))->toBe('99');
});

it('displays quantities without trailing zeros', function () {
    expect(Qty::display('2.500'))->toBe('2.5')->and(Qty::display('3.000'))->toBe('3')->and(Qty::display(0))->toBe('0');
});

it('normalizes tanzanian phone numbers', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    ['0712345678', '255712345678'],
    ['+255 712 345 678', '255712345678'],
    ['255654321987', '255654321987'],
    ['712345678', '255712345678'],
    ['0812345678', null],
    ['12345', null],
]);

it('formats phone numbers for display', function () {
    expect(PhoneNumber::display('255712345678'))->toBe('0712 345 678');
});

it('validates and generates EAN-13 codes', function () {
    $code = BarcodeParser::ean13('620000000001');
    expect(strlen($code))->toBe(13)->and(BarcodeParser::validEan13($code))->toBeTrue()
        ->and(BarcodeParser::validEan13('6200000000010'))->toBe($code === '6200000000010');
});

it('parses weight and price embedded barcodes', function () {
    $weight = BarcodeParser::ean13('201234501250'); // 1.250 kg of product 12345
    $parsed = BarcodeParser::parseEmbedded($weight);
    expect($parsed['type'])->toBe('weight')->and($parsed['code'])->toBe('12345')->and($parsed['weight'])->toBe('1.250');

    $price = BarcodeParser::ean13('251234504500'); // TSh 4,500
    expect(BarcodeParser::parseEmbedded($price))->toMatchArray(['type' => 'price', 'price' => '4500.00']);
    expect(BarcodeParser::parseEmbedded('6201234567890'))->toBeNull();
});
