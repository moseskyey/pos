<?php

namespace App\Enums;

enum AdjustmentReason: string implements HasLabel
{
    case Opening = 'opening';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Theft = 'theft';
    case Found = 'found';
    case Correction = 'correction';
    case InternalUse = 'internal_use';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening stock'),
            self::Damaged => __('Damaged'),
            self::Expired => __('Expired'),
            self::Theft => __('Theft / loss'),
            self::Found => __('Found'),
            self::Correction => __('Correction'),
            self::InternalUse => __('Internal use'),
        };
    }

    /** Default direction for the reason. */
    public function direction(): ?string
    {
        return match ($this) {
            self::Opening, self::Found => 'in',
            self::Damaged, self::Expired, self::Theft, self::InternalUse => 'out',
            self::Correction => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
