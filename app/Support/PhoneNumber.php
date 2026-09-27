<?php

namespace App\Support;

/**
 * Normalises Tanzanian phone numbers to the 2557XXXXXXXX / 2556XXXXXXXX format.
 */
class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $input);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '255')) {
            $local = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $local = substr($digits, 1);
        } else {
            $local = $digits;
        }

        if (strlen($local) !== 9 || ! in_array($local[0], ['6', '7'], true)) {
            return null;
        }

        return '255'.$local;
    }

    public static function isValid(?string $input): bool
    {
        return static::normalize($input) !== null;
    }

    /** Display format: "0712 345 678". */
    public static function display(?string $phone): string
    {
        $normalized = static::normalize($phone);
        if ($normalized === null) {
            return (string) $phone;
        }
        $local = '0'.substr($normalized, 3);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }
}
