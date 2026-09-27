<?php

namespace App\Support;

/**
 * Parses scale / price-embedded EAN-13 barcodes (prefix "2").
 *
 * Layout used by most retail scales: 2 PPPPP VVVVV C
 *   - "2" + 1 digit type flag (20–29) is the prefix
 *   - PPPPP  = product code (matched against a product barcode "2PPPPP" or "2XPPPPP")
 *   - VVVVV  = weight in grams (types 20–24) or price in TZS (types 25–29)
 *   - C      = check digit
 */
class BarcodeParser
{
    /**
     * @return array{type: string, code: string, lookup: string, weight?: string, price?: string}|null
     */
    public static function parseEmbedded(string $barcode): ?array
    {
        $barcode = trim($barcode);
        if (! preg_match('/^2\d{12}$/', $barcode) || ! static::validEan13($barcode)) {
            return null;
        }

        $flag = (int) substr($barcode, 1, 1);
        $code = substr($barcode, 2, 5);
        $value = substr($barcode, 7, 5);
        $lookup = substr($barcode, 0, 7);

        if ($flag <= 4) {
            return [
                'type' => 'weight',
                'code' => $code,
                'lookup' => $lookup,
                'weight' => Qty::div((int) $value, 1000, 3),
            ];
        }

        return [
            'type' => 'price',
            'code' => $code,
            'lookup' => $lookup,
            'price' => Money::round((int) $value),
        ];
    }

    public static function validEan13(string $barcode): bool
    {
        if (! preg_match('/^\d{13}$/', $barcode)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $barcode[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10 === (int) $barcode[12];
    }

    /** Generate an EAN-13 with a valid check digit from a 12-digit body. */
    public static function ean13(string $body): string
    {
        $body = str_pad(substr(preg_replace('/\D/', '', $body), 0, 12), 12, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - ($sum % 10)) % 10);
    }
}
