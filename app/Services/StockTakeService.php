<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Stock take / cycle count: create & freeze snapshot → count → submit → post variances.
 */
class StockTakeService
{
    public function __construct(protected StockService $stock, protected DocumentNumberService $numbers) {}

    public function create(int $branchId, User $user, ?int $categoryId = null, ?string $note = null): StockTake
    {
        return DB::transaction(function () use ($branchId, $user, $categoryId, $note) {
            $take = StockTake::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'number' => $this->numbers->next('stock_take', $branchId),
                'scope' => $categoryId ? 'category' : 'full',
                'category_id' => $categoryId,
                'status' => 'counting',
                'note' => $note,
                'created_by' => $user->id,
                'frozen_at' => now(),
            ]);

            $categoryIds = $categoryId ? Category::find($categoryId)?->descendantIds() : null;
            $products = Product::query()->active()->sellable()->where('track_stock', true)
                ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->get(['id', 'cost_price']);
            $balances = $this->stock->availableMany($branchId, $products->pluck('id')->all());

            $now = now();
            $rows = $products->map(fn ($p) => [
                'stock_take_id' => $take->id,
                'product_id' => $p->id,
                'expected_quantity' => $balances[$p->id] ?? 0,
                'counted_quantity' => null,
                'unit_cost' => $p->cost_price,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                StockTakeItem::insert($chunk);
            }

            activity('stock')->performedOn($take)->withProperties(['products' => count($rows)])->log('Stock take started');

            return $take;
        });
    }

    public function count(StockTake $take, int $productId, string|int|float $quantity, User $user, bool $add = false): StockTakeItem
    {
        if (! $take->isEditable()) {
            throw new BusinessRuleException(__('This stock take is no longer open for counting.'));
        }
        $item = $take->items()->where('product_id', $productId)->first();
        if (! $item) {
            $product = Product::findOrFail($productId);
            $item = $take->items()->create([
                'product_id' => $product->id,
                'expected_quantity' => $this->stock->available($take->branch_id, $product->id),
                'unit_cost' => $product->cost_price,
            ]);
        }
        $item->update([
            'counted_quantity' => $add ? Qty::add($item->counted_quantity ?? 0, $quantity) : Qty::round($quantity),
            'counted_by' => $user->id,
            'counted_at' => now(),
        ]);

        return $item;
    }

    public function submit(StockTake $take, User $user): StockTake
    {
        if ($take->status !== 'counting') {
            throw new BusinessRuleException(__('Only open stock takes can be submitted.'));
        }
        $take->update(['status' => 'submitted', 'submitted_at' => now()]);
        activity('stock')->causedBy($user)->performedOn($take)->log('Stock take submitted');

        return $take;
    }

    /**
     * Post variances. Uncounted items are left unchanged. Stock that moved after
     * the snapshot is preserved: the adjustment equals counted − expected.
     */
    public function post(StockTake $take, User $approver): StockTake
    {
        return DB::transaction(function () use ($take, $approver) {
            $take = StockTake::withoutGlobalScopes()->lockForUpdate()->findOrFail($take->id);
            if (! in_array($take->status, ['counting', 'submitted'], true)) {
                throw new BusinessRuleException(__('This stock take has already been closed.'));
            }

            foreach ($take->items()->with('product')->whereNotNull('counted_quantity')->get() as $item) {
                $variance = Qty::sub($item->counted_quantity, $item->expected_quantity);
                if (Qty::isZero($variance)) {
                    continue;
                }
                $current = $this->stock->available($take->branch_id, $item->product_id);
                $this->stock->setCounted($take->branch_id, $item->product, Qty::add($current, $variance), $take, __('Stock take :number', ['number' => $take->number]));
            }

            $take->update(['status' => 'posted', 'approved_by' => $approver->id, 'posted_at' => now()]);
            activity('stock')->causedBy($approver)->performedOn($take)->log('Stock take posted');

            return $take;
        });
    }

    public function cancel(StockTake $take, User $user): StockTake
    {
        if (! in_array($take->status, ['counting', 'submitted'], true)) {
            throw new BusinessRuleException(__('This stock take has already been closed.'));
        }
        $take->update(['status' => 'cancelled']);
        activity('stock')->causedBy($user)->performedOn($take)->log('Stock take cancelled');

        return $take;
    }
}
