<?php

namespace App\Enums;

enum SaleStatus: string implements HasLabel
{
    case Completed = 'completed';
    case Held = 'held';
    case Voided = 'voided';
    case Quotation = 'quotation';
    case Layaway = 'layaway';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::Held => __('Held'),
            self::Voided => __('Voided'),
            self::Quotation => __('Quotation'),
            self::Layaway => __('Layaway'),
            self::Converted => __('Converted'),
        };
    }

    public static function options(): array
    {
        return collect([self::Completed, self::Voided, self::Layaway])->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
