<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Automatic promotions. The till shows the discount for display; SaleService
 * recalculates it on checkout, so the browser can never choose a promotion.
 */
class PromotionService
{
    /** @var array<string, Collection<int, Promotion>> */
    protected array $cache = [];

    /** Promotions running now (dates, weekdays, time window, branch), best first. */
    public function running(?int $branchId, ?Carbon $at = null): Collection
    {
        if (! feature('promotions')) {
            return collect();
        }
        $at ??= now();
        $key = ($branchId ?? 0).'|'.$at->format('Y-m-d H:i');

        return $this->cache[$key] ??= Promotion::query()->current($at)->with('targets')
            ->orderByDesc('priority')->orderBy('id')->get()
            ->filter(fn (Promotion $p) => $p->runsAt($at, $branchId))
            ->values();
    }

    /**
     * Best promotion discount for one cart line (fixed amount for the whole line).
     *
     * @return array{discount: string, promotion: ?Promotion}
     */
    public function lineDiscount(Product $product, string|int|float $qty, string|int|float $unitPrice, ?int $branchId, ?Carbon $at = null): array
    {
        $best = ['discount' => '0.00', 'promotion' => null];
        foreach ($this->running($branchId, $at) as $promotion) {
            if (! $promotion->appliesToProduct($product)) {
                continue;
            }
            $discount = $this->discountFor($promotion, (string) $qty, (string) $unitPrice);
            if (Money::gt($discount, $best['discount'])) {
                $best = ['discount' => $discount, 'promotion' => $promotion];
            }
        }

        return $best;
    }

    public function discountFor(Promotion $promotion, string $qty, string $unitPrice): string
    {
        if (! Qty::isPositive($qty) || ! Money::isPositive($unitPrice)) {
            return '0.00';
        }
        if ($promotion->min_qty !== null && Qty::isPositive($promotion->min_qty) && Qty::lt($qty, $promotion->min_qty)) {
            return '0.00';
        }
        $gross = Money::mul($qty, $unitPrice);
        $whole = (int) floor((float) $qty);

        $discount = match ($promotion->type) {
            'percent' => Money::percent($gross, Money::min($promotion->value, 100)),
            'amount' => Money::mul($qty, Money::min($promotion->value, $unitPrice)),
            'buy_get' => $this->buyGetDiscount($whole, (int) $promotion->buy_qty, (int) $promotion->get_qty, $unitPrice),
            'multi_price' => $this->multiPriceDiscount($whole, (int) $promotion->buy_qty, (string) $promotion->value, $unitPrice),
            default => '0.00',
        };

        return Money::max('0.00', Money::min(Money::round($discount), $gross));
    }

    /** Buy X get Y free: every full group of X+Y items has Y free. */
    protected function buyGetDiscount(int $qty, int $buy, int $get, string $unitPrice): string
    {
        if ($buy < 1 || $get < 1) {
            return '0.00';
        }
        $free = intdiv($qty, $buy + $get) * $get;

        return Money::mul($free, $unitPrice);
    }

    /** N for a price: each full group of N items costs the promotion price. */
    protected function multiPriceDiscount(int $qty, int $n, string $groupPrice, string $unitPrice): string
    {
        if ($n < 1) {
            return '0.00';
        }
        $saving = Money::sub(Money::mul($n, $unitPrice), $groupPrice);

        return Money::isPositive($saving) ? Money::mul(intdiv($qty, $n), $saving) : '0.00';
    }

    /**
     * Create or update a promotion with its targets.
     *
     * @param  array<string, mixed>  $data  validated PromotionRequest data
     */
    public function save(array $data, User $user, ?Promotion $promotion = null): Promotion
    {
        return DB::transaction(function () use ($data, $user, $promotion) {
            $targets = $data['applies_to'] === 'products' ? ($data['product_ids'] ?? [])
                : ($data['applies_to'] === 'categories' ? ($data['category_ids'] ?? []) : []);
            $attributes = collect($data)->except(['product_ids', 'category_ids'])->all();
            $attributes['branch_ids'] = ! empty($data['branch_ids']) ? array_map('intval', $data['branch_ids']) : null;
            $attributes['days_of_week'] = ! empty($data['days_of_week']) ? array_map('intval', $data['days_of_week']) : null;

            $promotion ??= new Promotion(['created_by' => $user->id]);
            $promotion->fill($attributes)->save();

            $promotion->targets()->delete();
            $column = $data['applies_to'] === 'products' ? 'product_id' : 'category_id';
            foreach (array_unique($targets) as $id) {
                $promotion->targets()->create([$column => (int) $id]);
            }
            $this->cache = [];

            return $promotion->load('targets');
        });
    }
}
