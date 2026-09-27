<?php

namespace App\Support;

use Brick\Math\RoundingMode;

/**
 * Quantity helpers (3 decimals to support kg / litre items).
 */
class Qty extends Money
{
    public const SCALE = 3;

    /** Drop trailing zeros for display: "2.500" → "2.5", "3.000" → "3". */
    public static function display(mixed $value): string
    {
        $value = (string) static::of($value)->toScale(3, RoundingMode::HalfUp);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '-0' ? '0' : $value;
    }
}
