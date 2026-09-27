<?php

namespace App\Enums;

enum MovementType: string implements HasLabel
{
    case Opening = 'opening';
    case Sale = 'sale';
    case Return = 'return';
    case Void = 'void';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case StockTake = 'stock_take';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening stock'),
            self::Sale => __('Sale'),
            self::Return => __('Customer return'),
            self::Void => __('Sale voided'),
            self::Purchase => __('Purchase (GRN)'),
            self::PurchaseReturn => __('Return to supplier'),
            self::AdjustmentIn => __('Adjustment in'),
            self::AdjustmentOut => __('Adjustment out'),
            self::TransferOut => __('Transfer out'),
            self::TransferIn => __('Transfer in'),
            self::StockTake => __('Stock take'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sale, self::AdjustmentOut, self::TransferOut, self::PurchaseReturn => 'danger',
            self::Purchase, self::AdjustmentIn, self::TransferIn, self::Opening => 'success',
            self::Return, self::Void => 'info',
            self::StockTake => 'warning',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
