<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Serial / IMEI numbers for products that track them: registered when stock
 * arrives, marked sold (with customer and warranty end) at checkout, put
 * back on void or return.
 */
class SerialService
{
    /** Split pasted or scanned text into clean, unique serials. @return array<int, string> */
    public static function parse(string|array|null $input): array
    {
        $parts = is_array($input) ? $input : preg_split('/[\r\n,;]+/', (string) $input);

        return array_values(array_unique(array_filter(array_map(fn ($s) => ProductSerial::normalize($s), $parts))));
    }

    /**
     * Put serials into stock at a branch (on a goods receipt or by hand).
     *
     * @param  array<int, string>  $serials
     * @return Collection<int, ProductSerial>
     */
    public function register(Product $product, int $branchId, array $serials, User $user, ?GoodsReceipt $receipt = null): Collection
    {
        $serials = static::parse($serials);
        if (! $serials) {
            return collect();
        }
        $taken = ProductSerial::withoutGlobalScopes()->where('product_id', $product->id)->whereIn('serial', $serials)->pluck('serial');
        if ($taken->isNotEmpty()) {
            throw new BusinessRuleException(__('Already recorded for :p: :s', ['p' => $product->name, 's' => $taken->take(5)->join(', ')]));
        }

        $created = collect($serials)->map(fn ($serial) => ProductSerial::withoutGlobalScopes()->create([
            'product_id' => $product->id, 'branch_id' => $branchId, 'serial' => $serial, 'status' => 'in_stock', 'goods_receipt_id' => $receipt?->id,
        ]));
        activity('stock')->causedBy($user)->performedOn($product)->withProperties(['branch_id' => $branchId, 'count' => $created->count()])->log('Serial numbers registered');

        return $created;
    }

    /**
     * Mark the serials of a sold line. The number of serials must match the
     * quantity. A serial never registered is recorded now (shops that don't
     * register serials on receipt still get warranty tracking).
     *
     * @param  array<int, string>  $serials
     */
    public function sell(Sale $sale, SaleItem $item, Product $product, array $serials, Carbon $soldAt, bool $offline = false): void
    {
        $serials = static::parse($serials);
        $qty = (float) $item->base_quantity;
        if ($qty != floor($qty) || count($serials) !== (int) $qty) {
            throw new BusinessRuleException(trans_choice('Scan :count serial / IMEI number for :p.|Scan :count serial / IMEI numbers for :p.', (int) $qty, ['p' => $product->name]));
        }
        $warranty = $product->warranty_months ? $soldAt->copy()->addMonthsNoOverflow($product->warranty_months)->toDateString() : null;

        foreach ($serials as $serial) {
            $record = ProductSerial::withoutGlobalScopes()->where('product_id', $product->id)->where('serial', $serial)->lockForUpdate()->first();
            if ($record && ! $offline) {
                if ($record->status !== 'in_stock') {
                    throw new BusinessRuleException(__('Serial :s of :p is already :status.', ['s' => $serial, 'p' => $product->name, 'status' => __($record->status === 'sold' ? 'sold' : 'marked defective')]));
                }
                if ($record->branch_id !== $sale->branch_id) {
                    throw new BusinessRuleException(__('Serial :s is in stock at another branch.', ['s' => $serial]));
                }
            }
            $values = [
                'branch_id' => $sale->branch_id, 'status' => 'sold', 'sale_id' => $sale->id, 'sale_item_id' => $item->id,
                'customer_id' => $sale->customer_id, 'sold_at' => $soldAt, 'warranty_until' => $warranty,
            ];
            $record
                ? $record->update($values)
                : ProductSerial::withoutGlobalScopes()->create($values + ['product_id' => $product->id, 'serial' => $serial, 'note' => __('Recorded at sale')]);
        }
    }

    /** Voided sale: its serials go back into stock. */
    public function restoreForVoid(Sale $sale): void
    {
        ProductSerial::withoutGlobalScopes()->where('sale_id', $sale->id)->where('status', 'sold')->update([
            'status' => 'in_stock', 'sale_id' => null, 'sale_item_id' => null, 'customer_id' => null, 'sold_at' => null, 'warranty_until' => null,
        ]);
    }

    /**
     * Returned units: back into stock, or defective for damaged returns.
     * When only some units come back the serials must be named.
     *
     * @param  array<int, string>|null  $serials
     */
    public function returnItem(SaleItem $item, string $qty, string $condition, ?array $serials = null): void
    {
        $sold = ProductSerial::withoutGlobalScopes()->where('sale_item_id', $item->id)->where('status', 'sold')->lockForUpdate()->get();
        if ($sold->isEmpty()) {
            return;
        }
        $count = (int) round((float) $qty * (float) $item->conversion_factor);
        $chosen = $serials ? $sold->whereIn('serial', static::parse($serials)) : ($sold->count() === $count ? $sold : collect());
        if ($chosen->count() !== $count) {
            throw new BusinessRuleException(trans_choice('Choose the :count serial number being returned for :p.|Choose the :count serial numbers being returned for :p.', $count, ['p' => $item->name]));
        }
        foreach ($chosen as $record) {
            $record->update([
                'status' => $condition === 'restock' ? 'in_stock' : 'defective',
                'note' => __('Returned from :n', ['n' => $item->sale?->number]),
                'sale_id' => null, 'sale_item_id' => null, 'customer_id' => null, 'sold_at' => null, 'warranty_until' => null,
            ]);
        }
    }

    public function find(string $serial): ?ProductSerial
    {
        return ProductSerial::withoutGlobalScopes()->with(['product', 'sale', 'customer'])->where('serial', ProductSerial::normalize($serial))->latest('id')->first();
    }
}
