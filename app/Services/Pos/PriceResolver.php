<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Qty;

/**
 * Resolves the selling price for a product/unit/quantity/customer:
 * wholesale applies to wholesale customers or at/above the min quantity.
 */
class PriceResolver
{
    /** @return array{price: string, tier: string} */
    public function resolve(Product $product, ?ProductUnit $unit, string|int|float $quantity, ?Customer $customer = null): array
    {
        $retail = (string) ($unit ? $unit->retail_price : $product->retail_price);
        $wholesale = $unit ? $unit->wholesale_price : $product->wholesale_price;
        $factor = $unit ? (string) $unit->factor : '1';

        if ($wholesale !== null && Qty::isPositive($wholesale)) {
            $baseQty = Qty::mul($quantity, $factor);
            $qualifies = ($customer && $customer->isWholesale())
                || ($product->wholesale_min_qty !== null && Qty::isPositive($product->wholesale_min_qty) && Qty::gte($baseQty, $product->wholesale_min_qty));
            if ($qualifies) {
                return ['price' => (string) $wholesale, 'tier' => 'wholesale'];
            }
        }

        return ['price' => $retail, 'tier' => 'retail'];
    }
}
