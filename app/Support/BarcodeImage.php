<?php

namespace App\Support;

use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;
use Picqer\Barcode\Types\TypeEan13;
use Throwable;

class BarcodeImage
{
    public static function svg(string $code, float $width = 180, float $height = 40): string
    {
        try {
            $type = BarcodeParser::validEan13($code) ? new TypeEan13 : new TypeCode128;
            $barcode = $type->getBarcode($code);
        } catch (Throwable) {
            $barcode = (new TypeCode128)->getBarcode($code);
        }

        return (new SvgRenderer)->render($barcode, $width, $height);
    }

    public static function dataUri(string $code, float $width = 180, float $height = 40): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(static::svg($code, $width, $height));
    }
}
