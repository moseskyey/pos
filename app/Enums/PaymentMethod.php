<?php

namespace App\Enums;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case CashUsd = 'cash_usd';
    case Mpesa = 'mpesa';
    case TigoPesa = 'tigopesa';
    case Airtel = 'airtel';
    case HaloPesa = 'halopesa';
    case Card = 'card';
    case Bank = 'bank';
    case Credit = 'credit';
    case StoreCredit = 'store_credit';
    case GiftCard = 'gift_card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::CashUsd => __('Cash (USD)'),
            self::Mpesa => 'M-Pesa',
            self::TigoPesa => 'Mixx by Yas',
            self::Airtel => 'Airtel Money',
            self::HaloPesa => 'HaloPesa',
            self::Card => __('Card'),
            self::Bank => __('Bank transfer'),
            self::Credit => __('Credit (account)'),
            self::StoreCredit => __('Store credit'),
            self::GiftCard => __('Gift card / voucher'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'bi-cash-stack',
            self::CashUsd => 'bi-currency-dollar',
            self::Mpesa, self::TigoPesa, self::Airtel, self::HaloPesa => 'bi-phone',
            self::Card => 'bi-credit-card',
            self::Bank => 'bi-bank',
            self::Credit => 'bi-journal-text',
            self::StoreCredit => 'bi-wallet2',
            self::GiftCard => 'bi-gift',
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [self::Mpesa, self::TigoPesa, self::Airtel, self::HaloPesa], true);
    }

    /** Physical cash in the drawer (can be over-tendered and give change). */
    public function isCash(): bool
    {
        return in_array($this, [self::Cash, self::CashUsd], true);
    }

    public function isForeign(): bool
    {
        return $this === self::CashUsd;
    }

    public function needsReference(): bool
    {
        return $this->isMobileMoney() || in_array($this, [self::Card, self::Bank], true);
    }

    /**
     * Paid from a balance the business already holds (customer account, store
     * credit, gift card), not new money. These can't pay expenses, suppliers,
     * customer debts or layaway deposits.
     */
    public function isAccount(): bool
    {
        return in_array($this, [self::Credit, self::StoreCredit, self::GiftCard], true);
    }

    /** Methods enabled in settings (gift cards follow Settings → Features). */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m) => $m === self::GiftCard
            ? feature('gift_cards')
            : (bool) setting('payments.'.$m->value, $m !== self::CashUsd)));
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
