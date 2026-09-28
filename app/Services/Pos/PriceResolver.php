<?php

namespace App\Services\Pos;

use App\Models\BranchPrice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Qty;

/**
 * Resolves the selling price for a product/unit/quantity/customer:
 * a branch price (when switched on) replaces the product/unit prices, and
 * wholesale applies to wholesale customers or at/above the min quantity.
 */
class PriceResolver
{
    /** @var array<string, BranchPrice|null> */
    protected array $branchPrices = [];

    /** @return array{price: string, tier: string, list_price: string} */
    public function resolve(Product $product, ?ProductUnit $unit, string|int|float $quantity, ?Customer $customer = null, ?int $branchId = null): array
    {
        $retail = (string) ($unit ? $unit->retail_price : $product->retail_price);
        $wholesale = $unit ? $unit->wholesale_price : $product->wholesale_price;
        $factor = $unit ? (string) $unit->factor : '1';

        if ($override = $this->branchPrice($branchId, $product, $unit)) {
            $retail = (string) $override->retail_price;
            $wholesale = $override->wholesale_price ?? $wholesale;
        }

        if ($wholesale !== null && Qty::isPositive($wholesale)) {
            $baseQty = Qty::mul($quantity, $factor);
            $qualifies = ($customer && $customer->isWholesale())
                || ($product->wholesale_min_qty !== null && Qty::isPositive($product->wholesale_min_qty) && Qty::gte($baseQty, $product->wholesale_min_qty));
            if ($qualifies) {
                return ['price' => (string) $wholesale, 'tier' => 'wholesale', 'list_price' => $retail];
            }
        }

        return ['price' => $retail, 'tier' => 'retail', 'list_price' => $retail];
    }

    public function branchPrice(?int $branchId, Product $product, ?ProductUnit $unit = null): ?BranchPrice
    {
        if (! $branchId || ! feature('branch_prices')) {
            return null;
        }
        $key = $branchId.':'.$product->id.':'.($unit?->id ?? 0);

        if (! array_key_exists($key, $this->branchPrices)) {
            $this->branchPrices[$key] = BranchPrice::query()
                ->where('branch_id', $branchId)->where('product_id', $product->id)
                ->where('product_unit_id', $unit?->id)
                ->first();
        }

        return $this->branchPrices[$key];
    }
}
