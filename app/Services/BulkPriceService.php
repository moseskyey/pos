<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BulkPriceService
{
    public function __construct(protected ProductService $products) {}

    public function query(array $filters): Builder
    {
        return Product::query()->sellable()
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->whereIn('category_id', Category::find($id)?->descendantIds() ?? [$id]))
            ->when($filters['brand_id'] ?? null, fn ($q, $id) => $q->where('brand_id', $id))
            ->when(! ($filters['include_inactive'] ?? false), fn ($q) => $q->where('is_active', true));
    }

    public function newPrice(string $current, string $mode, string $value, int $rounding = 0): string
    {
        $new = match ($mode) {
            'percent' => Money::add($current, Money::percent($current, $value)),
            'fixed' => Money::add($current, $value),
            'set' => Money::round($value),
            default => $current,
        };
        if (Money::isNegative($new)) {
            $new = '0.00';
        }

        return $rounding > 0 ? Money::roundToNearest($new, $rounding) : $new;
    }

    /** @return int number of products changed */
    public function apply(array $data, User $user): int
    {
        $fields = $data['field'] === 'both' ? ['retail_price', 'wholesale_price'] : [$data['field']];
        $count = 0;

        DB::transaction(function () use ($data, $user, $fields, &$count) {
            $this->query($data)->chunkById(200, function ($products) use ($data, $user, $fields, &$count) {
                foreach ($products as $product) {
                    $prices = [];
                    foreach ($fields as $field) {
                        if ($product->{$field} === null) {
                            continue;
                        }
                        $prices[$field] = $this->newPrice((string) $product->{$field}, $data['mode'], (string) $data['value'], (int) ($data['rounding'] ?? 0));
                    }
                    if ($prices) {
                        $this->products->changePrices($product, $prices, $user, $data['reason'] ?? __('Bulk price update'));
                        $count++;
                    }
                }
            });
        });

        activity('products')->causedBy($user)->withProperties($data + ['products' => $count])->log('Bulk price update');

        return $count;
    }
}
