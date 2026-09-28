<?php

namespace App\Services\Pos;

use App\Support\Money;
use App\Support\Qty;

/**
 * Pure cart pricing: promotion discounts, manual line discounts, proportional
 * cart discount, VAT (inclusive or exclusive) and cash rounding. Used by the POS screen for
 * display and by SaleService as the authoritative calculation.
 */
class CartCalculator
{
    public function __construct(
        protected bool $pricesIncludeVat = true,
        protected int $rounding = 0,
    ) {}

    public static function fromSettings(): self
    {
        return new self((bool) setting('tax.prices_include_vat', true), (int) setting('pos.rounding', 0));
    }

    /**
     * A line's promo_discount (a fixed amount set by PromotionService) comes off
     * first; a manual line discount then applies to what is left.
     *
     * @param  array<int|string, array{qty:mixed, unit_price:mixed, promo_discount?:mixed, discount_type?:?string, discount_value?:mixed, tax_rate?:mixed}>  $lines
     * @return array{lines: array, subtotal: string, promo_discounts: string, line_discounts: string, cart_discount: string, discount_total: string, tax_total: string, rounding: string, total: string, items: string}
     */
    public function calculate(array $lines, ?string $cartDiscountType = null, mixed $cartDiscountValue = null): array
    {
        $out = [];
        $subtotal = '0';
        $lineDiscounts = '0';
        $promoDiscounts = '0';
        $afterLine = '0';
        $items = '0';

        foreach ($lines as $key => $line) {
            $qty = Qty::round($line['qty'] ?? 0);
            $gross = Money::mul($qty, $line['unit_price'] ?? 0);
            $promo = Money::max('0.00', Money::min(Money::round($line['promo_discount'] ?? 0), $gross));
            $afterPromo = Money::sub($gross, $promo);
            $discount = $this->discount($afterPromo, $line['discount_type'] ?? null, $line['discount_value'] ?? null);
            $lineTotal = Money::sub($afterPromo, $discount);

            $out[$key] = [
                'qty' => $qty,
                'gross' => $gross,
                'promo_discount' => $promo,
                'discount_base' => $afterPromo,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
                'tax_rate' => Money::round($line['tax_rate'] ?? 0),
            ];
            $subtotal = Money::add($subtotal, $gross);
            $lineDiscounts = Money::add($lineDiscounts, $discount);
            $promoDiscounts = Money::add($promoDiscounts, $promo);
            $afterLine = Money::add($afterLine, $lineTotal);
            $items = Qty::add($items, $qty);
        }

        $cartDiscount = $this->discount($afterLine, $cartDiscountType, $cartDiscountValue);

        // Allocate the cart discount proportionally; the last line absorbs rounding.
        $allocated = '0';
        $keys = array_keys($out);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $share = Money::sub($cartDiscount, $allocated);
            } else {
                $share = Money::isZero($afterLine) ? '0.00' : Money::div(Money::mul($out[$key]['line_total'], $cartDiscount), $afterLine);
            }
            $allocated = Money::add($allocated, $share);
            $taxable = Money::sub($out[$key]['line_total'], $share);
            $out[$key]['cart_discount_share'] = $share;
            $out[$key]['net_total'] = $taxable;
            $out[$key]['tax_amount'] = $this->pricesIncludeVat
                ? Money::taxFromInclusive($taxable, $out[$key]['tax_rate'])
                : Money::percent($taxable, $out[$key]['tax_rate']);
        }

        $taxTotal = Money::sum($out, 'tax_amount');
        $net = Money::sub($afterLine, $cartDiscount);
        $total = $this->pricesIncludeVat ? $net : Money::add($net, $taxTotal);
        $rounded = $this->rounding > 0 ? Money::roundToNearest($total, $this->rounding) : $total;

        return [
            'lines' => $out,
            'subtotal' => $subtotal,
            'promo_discounts' => Money::round($promoDiscounts),
            'line_discounts' => $lineDiscounts,
            'cart_discount' => $cartDiscount,
            'discount_total' => Money::add(Money::add($lineDiscounts, $cartDiscount), $promoDiscounts),
            'tax_total' => $taxTotal,
            'rounding' => Money::sub($rounded, $total),
            'total' => $rounded,
            'items' => $items,
        ];
    }

    public function discount(string $base, ?string $type, mixed $value): string
    {
        if (! $type || $value === null || $value === '' || Money::lte($value, 0)) {
            return '0.00';
        }
        $amount = $type === 'percent' ? Money::percent($base, Money::min($value, 100)) : Money::round($value);

        return Money::min($amount, $base);
    }

    /** Discount as a percentage of the base amount (for limit checks). */
    public static function discountPercent(string $base, string $discount): string
    {
        return Money::isZero($base) ? '0.00' : Money::div(Money::mul($discount, 100), $base);
    }
}
