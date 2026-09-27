<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Integer-safe decimal helpers. All values are passed around as numeric strings
 * and never as floats. Rounding happens only when explicitly requested.
 */
class Money
{
    public const SCALE = 2;

    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if (is_float($value)) {
            return BigDecimal::fromFloatShortest($value);
        }
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
            if ($value === '' || ! is_numeric($value)) {
                return BigDecimal::zero();
            }
        }

        return BigDecimal::of($value);
    }

    public static function round(mixed $value, ?int $scale = null): string
    {
        return (string) static::of($value)->toScale($scale ?? static::SCALE, RoundingMode::HalfUp);
    }

    public static function add(mixed ...$values): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus(static::of($value));
        }

        return static::round($sum);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return static::round(static::of($a)->minus(static::of($b)));
    }

    public static function mul(mixed $a, mixed $b): string
    {
        return static::round(static::of($a)->multipliedBy(static::of($b)));
    }

    public static function div(mixed $a, mixed $b, ?int $scale = null): string
    {
        $divisor = static::of($b);
        if ($divisor->isZero()) {
            return static::round(0, $scale);
        }

        return (string) static::of($a)->dividedBy($divisor, $scale ?? static::SCALE, RoundingMode::HalfUp);
    }

    /** amount × rate / 100 */
    public static function percent(mixed $amount, mixed $rate): string
    {
        return static::round(static::of($amount)->multipliedBy(static::of($rate))->dividedBy(100, 6, RoundingMode::HalfUp));
    }

    /** Tax contained in a VAT-inclusive gross amount: gross × rate / (100 + rate). */
    public static function taxFromInclusive(mixed $gross, mixed $rate): string
    {
        $rate = static::of($rate);
        if ($rate->isZero()) {
            return static::round(0);
        }

        return static::round(static::of($gross)->multipliedBy($rate)->dividedBy($rate->plus(100), 6, RoundingMode::HalfUp));
    }

    /** Round to the nearest multiple (e.g. 50 or 100 TZS). */
    public static function roundToNearest(mixed $value, int $nearest): string
    {
        if ($nearest <= 1) {
            return static::round($value);
        }
        $units = static::of($value)->dividedBy($nearest, 0, RoundingMode::HalfUp);

        return static::round($units->multipliedBy($nearest));
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return static::of($a)->compareTo(static::of($b));
    }

    public static function gt(mixed $a, mixed $b): bool
    {
        return static::cmp($a, $b) > 0;
    }

    public static function gte(mixed $a, mixed $b): bool
    {
        return static::cmp($a, $b) >= 0;
    }

    public static function lt(mixed $a, mixed $b): bool
    {
        return static::cmp($a, $b) < 0;
    }

    public static function lte(mixed $a, mixed $b): bool
    {
        return static::cmp($a, $b) <= 0;
    }

    public static function isZero(mixed $a): bool
    {
        return static::of($a)->isZero();
    }

    public static function isPositive(mixed $a): bool
    {
        return static::of($a)->isPositive();
    }

    public static function isNegative(mixed $a): bool
    {
        return static::of($a)->isNegative();
    }

    public static function min(mixed $a, mixed $b): string
    {
        return static::lt($a, $b) ? static::round($a) : static::round($b);
    }

    public static function max(mixed $a, mixed $b): string
    {
        return static::gt($a, $b) ? static::round($a) : static::round($b);
    }

    public static function negate(mixed $a): string
    {
        return static::round(static::of($a)->negated());
    }

    public static function abs(mixed $a): string
    {
        return static::round(static::of($a)->abs());
    }

    /** Sum a column / callback over a collection or array. */
    public static function sum(iterable $items, string|callable|null $key = null): string
    {
        $sum = BigDecimal::zero();
        foreach ($items as $item) {
            $value = match (true) {
                $key === null => $item,
                is_callable($key) => $key($item),
                default => data_get($item, $key),
            };
            $sum = $sum->plus(static::of($value));
        }

        return static::round($sum);
    }

    /** Human format, e.g. "TSh 12,500". */
    public static function format(mixed $value, bool $withSymbol = true, ?int $decimals = null): string
    {
        $decimals ??= (int) setting('currency.decimals', 0);
        $rounded = static::of($value)->toScale($decimals, RoundingMode::HalfUp);
        $negative = $rounded->isNegative();
        $string = (string) $rounded->abs();

        [$int, $frac] = array_pad(explode('.', $string), 2, '');
        $int = strrev(implode((string) setting('currency.thousands_separator', ','), str_split(strrev($int), 3)));
        $formatted = $decimals > 0 ? $int.setting('currency.decimal_separator', '.').$frac : $int;
        $formatted = ($negative ? '-' : '').$formatted;

        return $withSymbol ? setting('currency.symbol', 'TSh').' '.$formatted : $formatted;
    }
}
