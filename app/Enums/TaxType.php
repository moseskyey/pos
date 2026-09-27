<?php

namespace App\Enums;

enum TaxType: string implements HasLabel
{
    case Standard = 'standard';
    case Zero = 'zero';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('Standard (VAT :rate%)', ['rate' => setting('tax.vat_rate', 18)]),
            self::Zero => __('Zero-rated'),
            self::Exempt => __('Exempt'),
        };
    }

    public function rate(): string
    {
        return $this === self::Standard ? (string) setting('tax.vat_rate', 18) : '0';
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
