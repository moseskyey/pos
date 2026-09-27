<?php

namespace App\Support\EscPos;

/**
 * Minimal ESC/POS command builder for 58mm / 80mm thermal printers
 * (Epson TM, Xprinter, Rongta, Sunmi and compatibles). Produces raw bytes;
 * the browser sends them to the printer over WebUSB / WebSerial.
 */
class Printer
{
    public const ESC = "\x1B";

    public const GS = "\x1D";

    protected string $buffer = '';

    public function __construct(public int $width = 48)
    {
        $this->buffer = self::ESC.'@'.self::ESC.'t'."\x00"; // initialise, code page 437
    }

    public static function forPaper(string $paper): self
    {
        return new self($paper === '58mm' ? 32 : 48);
    }

    public function align(string $align): self
    {
        $this->buffer .= self::ESC.'a'.chr(match ($align) {
            'center' => 1, 'right' => 2, default => 0
        });

        return $this;
    }

    public function bold(bool $on = true): self
    {
        $this->buffer .= self::ESC.'E'.chr($on ? 1 : 0);

        return $this;
    }

    /** Double width + height text (headings, totals). */
    public function large(bool $on = true): self
    {
        $this->buffer .= self::GS.'!'.chr($on ? 0x11 : 0x00);

        return $this;
    }

    public function text(string $text): self
    {
        foreach (explode("\n", $text) as $line) {
            foreach ($this->wrap(self::ascii($line), $this->width) as $chunk) {
                $this->buffer .= $chunk."\n";
            }
        }

        return $this;
    }

    /** Label on the left, value on the right, padded to the paper width. */
    public function row(string $left, string $right, ?int $width = null): self
    {
        $width ??= $this->width;
        $left = self::ascii($left);
        $right = self::ascii($right);
        $space = $width - mb_strlen($right) - 1;
        $lines = $this->wrap($left, max($space, 8));
        $last = array_pop($lines);
        foreach ($lines as $line) {
            $this->buffer .= $line."\n";
        }
        $this->buffer .= str_pad((string) $last, max($width - mb_strlen($right), 0)).$right."\n";

        return $this;
    }

    public function rule(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, $this->width)."\n";

        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buffer .= self::ESC.'d'.chr(max(0, min($lines, 255)));

        return $this;
    }

    /** QR code (model 2) with the given data, using the printer's native QR support. */
    public function qr(string $data, int $size = 5): self
    {
        $len = strlen($data) + 3;
        $this->buffer .= self::GS.'(k'."\x04\x00\x31\x41\x32\x00"            // model 2
            .self::GS.'(k'."\x03\x00\x31\x43".chr(max(1, min($size, 16)))    // module size
            .self::GS.'(k'."\x03\x00\x31\x45\x31"                            // error correction M
            .self::GS.'(k'.chr($len % 256).chr(intdiv($len, 256))."\x31\x50\x30".$data
            .self::GS.'(k'."\x03\x00\x31\x51\x30";                            // print

        return $this;
    }

    /** Partial cut after feeding past the tear bar. */
    public function cut(): self
    {
        $this->buffer .= self::GS.'V'."\x42\x03";

        return $this;
    }

    /** Pulse the cash drawer (pin 2, ~50ms on / 500ms off). */
    public function openDrawer(): self
    {
        $this->buffer .= self::ESC.'p'."\x00\x19\xFA";

        return $this;
    }

    public function bytes(): string
    {
        return $this->buffer;
    }

    /** Thermal printers use single-byte code pages: transliterate to plain ASCII. */
    public static function ascii(string $text): string
    {
        $text = strtr($text, ['–' => '-', '—' => '-', '×' => 'x', '·' => '-', '’' => "'", '‘' => "'", '“' => '"', '”' => '"', '…' => '...', '€' => 'EUR']);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return preg_replace('/[^\x20-\x7E]/', '', $converted === false ? $text : $converted);
    }

    /** @return array<int, string> */
    protected function wrap(string $text, int $width): array
    {
        if ($text === '') {
            return [''];
        }

        return explode("\n", wordwrap($text, $width, "\n", true));
    }
}
