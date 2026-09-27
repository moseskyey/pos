<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The stock ledger. Every quantity change writes a stock_movements row and
 * updates the cached product_stocks balance in the same transaction, with the
 * balance row locked for update.
 */
class StockService
{
    /**
     * Add stock (purchase, return, transfer in, adjustment in, opening…).
     *
     * @return Collection<int, StockMovement>
     */
    public function receive(
        int $branchId,
        Product $product,
        string|int|float $quantity,
        MovementType $type,
        ?Model $reference = null,
        string|int|float|null $unitCost = null,
        ?string $batchNo = null,
        ?string $expiryDate = null,
        ?string $note = null,
        ?int $batchId = null,
    ): Collection {
        $quantity = Qty::round($quantity);
        if (! $product->track_stock || ! Qty::isPositive($quantity)) {
            return collect();
        }

        return $this->transaction(function () use ($branchId, $product, $quantity, $type, $reference, $unitCost, $batchNo, $expiryDate, $note, $batchId) {
            $unitCost = Money::round($unitCost ?? $product->cost_price);
            $batch = null;

            if ($product->track_batches) {
                $batch = $batchId
                    ? ProductBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId)
                    : $this->findOrCreateBatch($branchId, $product, $batchNo, $expiryDate, $unitCost);
                $batch->quantity = Qty::add($batch->quantity, $quantity);
                $batch->save();
            }

            return collect([$this->write($branchId, $product, $quantity, $type, $reference, $unitCost, $batch?->id, $note)]);
        });
    }

    /**
     * Remove stock (sale, transfer out, adjustment out, supplier return…).
     * Batch-tracked products are allocated FEFO (first expiry, first out).
     *
     * @return Collection<int, StockMovement> one movement per batch used
     */
    public function issue(
        int $branchId,
        Product $product,
        string|int|float $quantity,
        MovementType $type,
        ?Model $reference = null,
        bool $allowNegative = false,
        ?string $note = null,
        string|int|float|null $unitCost = null,
        ?int $batchId = null,
    ): Collection {
        $quantity = Qty::round($quantity);
        if (! $product->track_stock || ! Qty::isPositive($quantity)) {
            return collect();
        }

        return $this->transaction(function () use ($branchId, $product, $quantity, $type, $reference, $allowNegative, $note, $unitCost, $batchId) {
            $stock = $this->lockStock($branchId, $product->id);
            if (! $allowNegative && Qty::lt($stock->quantity, $quantity)) {
                throw new InsufficientStockException($product->name, (string) $stock->quantity, $quantity);
            }

            $unitCost = Money::round($unitCost ?? $product->cost_price);
            $movements = collect();

            if (! $product->track_batches) {
                $movements->push($this->write($branchId, $product, Qty::negate($quantity), $type, $reference, $unitCost, null, $note, $stock));

                return $movements;
            }

            $remaining = $quantity;
            $batches = ProductBatch::withoutGlobalScopes()
                ->where('branch_id', $branchId)->where('product_id', $product->id)->where('quantity', '>', 0)
                ->when($batchId, fn ($q) => $q->orderByRaw('id = ? desc', [$batchId]))
                ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')
                ->lockForUpdate()->get();

            foreach ($batches as $batch) {
                if (! Qty::isPositive($remaining)) {
                    break;
                }
                $take = Qty::min($batch->quantity, $remaining);
                $batch->quantity = Qty::sub($batch->quantity, $take);
                $batch->save();
                $movements->push($this->write($branchId, $product, Qty::negate($take), $type, $reference, $batch->cost_price ?: $unitCost, $batch->id, $note, $stock));
                $remaining = Qty::sub($remaining, $take);
            }

            // Stock without batch records (or negative selling) is taken from the un-batched pool.
            if (Qty::isPositive($remaining)) {
                $movements->push($this->write($branchId, $product, Qty::negate($remaining), $type, $reference, $unitCost, null, $note, $stock));
            }

            return $movements;
        });
    }

    /**
     * Set stock to an exact counted quantity (stock take). Writes the variance.
     */
    public function setCounted(int $branchId, Product $product, string|int|float $counted, ?Model $reference = null, ?string $note = null): ?StockMovement
    {
        return $this->transaction(function () use ($branchId, $product, $counted, $reference, $note) {
            $stock = $this->lockStock($branchId, $product->id);
            $variance = Qty::sub($counted, $stock->quantity);
            if (Qty::isZero($variance)) {
                return null;
            }

            if ($product->track_batches && Qty::isNegative($variance)) {
                return $this->issue($branchId, $product, Qty::abs($variance), MovementType::StockTake, $reference, true, $note)->first();
            }
            if ($product->track_batches) {
                return $this->receive($branchId, $product, $variance, MovementType::StockTake, $reference, null, 'COUNT-'.now()->format('Ymd'), null, $note)->first();
            }

            return $this->write($branchId, $product, $variance, MovementType::StockTake, $reference, $product->cost_price, null, $note, $stock);
        });
    }

    public function available(int $branchId, int $productId): string
    {
        return (string) (ProductStock::withoutGlobalScopes()->where('branch_id', $branchId)->where('product_id', $productId)->value('quantity') ?? '0.000');
    }

    /** @param  array<int>  $productIds  @return array<int, string> */
    public function availableMany(int $branchId, array $productIds): array
    {
        return ProductStock::withoutGlobalScopes()->where('branch_id', $branchId)->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id')->map(fn ($q) => (string) $q)->all();
    }

    protected function write(int $branchId, Product $product, string $signedQty, MovementType $type, ?Model $reference, string $unitCost, ?int $batchId, ?string $note, ?ProductStock $stock = null): StockMovement
    {
        $stock ??= $this->lockStock($branchId, $product->id);
        $stock->quantity = Qty::add($stock->quantity, $signedQty);
        $stock->save();

        return StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'batch_id' => $batchId,
            'type' => $type,
            'quantity' => $signedQty,
            'unit_cost' => $unitCost,
            'balance_after' => $stock->quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => Auth::id(),
            'note' => $note,
        ]);
    }

    protected function lockStock(int $branchId, int $productId): ProductStock
    {
        $stock = ProductStock::withoutGlobalScopes()->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first();
        if ($stock) {
            return $stock;
        }

        ProductStock::withoutGlobalScopes()->insertOrIgnore([
            'branch_id' => $branchId, 'product_id' => $productId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ProductStock::withoutGlobalScopes()->where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
    }

    protected function findOrCreateBatch(int $branchId, Product $product, ?string $batchNo, ?string $expiryDate, string $unitCost): ProductBatch
    {
        $batchNo = $batchNo ?: 'B'.now()->format('ymd');
        $batch = ProductBatch::withoutGlobalScopes()
            ->where('branch_id', $branchId)->where('product_id', $product->id)->where('batch_no', $batchNo)
            ->when($expiryDate, fn ($q) => $q->whereDate('expiry_date', $expiryDate), fn ($q) => $q->whereNull('expiry_date'))
            ->lockForUpdate()->first();

        return $batch ?? ProductBatch::withoutGlobalScopes()->create([
            'branch_id' => $branchId, 'product_id' => $product->id, 'batch_no' => $batchNo,
            'expiry_date' => $expiryDate, 'quantity' => 0, 'cost_price' => $unitCost,
        ]);
    }

    protected function transaction(callable $callback): mixed
    {
        return DB::transactionLevel() > 0 ? $callback() : DB::transaction($callback);
    }
}
