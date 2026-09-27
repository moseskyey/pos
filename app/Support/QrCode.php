<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    public static function svg(string $text, int $size = 160): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($text);

        return trim(preg_replace('/^<\?xml.*?\?>/', '', $svg));
    }

    public static function dataUri(string $text, int $size = 160): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(static::svg($text, $size));
    }
}
