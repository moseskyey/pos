<?php

namespace App\Enums;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case TigoPesa = 'tigopesa';
    case Airtel = 'airtel';
    case HaloPesa = 'halopesa';
    case Card = 'card';
    case Bank = 'bank';
    case Credit = 'credit';
    case StoreCredit = 'store_credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Mpesa => 'M-Pesa',
            self::TigoPesa => 'Mixx by Yas',
            self::Airtel => 'Airtel Money',
            self::HaloPesa => 'HaloPesa',
            self::Card => __('Card'),
            self::Bank => __('Bank transfer'),
            self::Credit => __('Credit (account)'),
            self::StoreCredit => __('Store credit'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'bi-cash-stack',
            self::Mpesa, self::TigoPesa, self::Airtel, self::HaloPesa => 'bi-phone',
            self::Card => 'bi-credit-card',
            self::Bank => 'bi-bank',
            self::Credit => 'bi-journal-text',
            self::StoreCredit => 'bi-wallet2',
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [self::Mpesa, self::TigoPesa, self::Airtel, self::HaloPesa], true);
    }

    public function needsReference(): bool
    {
        return $this->isMobileMoney() || in_array($this, [self::Card, self::Bank], true);
    }

    /** Methods enabled in settings. */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => (bool) setting('payments.'.$m->value, true)));
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
