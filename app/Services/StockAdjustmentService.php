<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(protected StockService $stock, protected DocumentNumberService $numbers) {}

    /**
     * @param  array<int, array{product_id:int, direction:string, quantity:mixed, unit_cost?:mixed, batch_no?:?string, expiry_date?:?string, note?:?string}>  $items
     */
    public function create(int $branchId, AdjustmentReason $reason, array $items, User $user, ?string $note = null): StockAdjustment
    {
        return DB::transaction(function () use ($branchId, $reason, $items, $user, $note) {
            $adjustment = StockAdjustment::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'number' => $this->numbers->next('adjustment', $branchId),
                'reason' => $reason,
                'status' => 'pending',
                'note' => $note,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $adjustment->items()->create([
                    'product_id' => $product->id,
                    'direction' => $item['direction'] ?? $reason->direction() ?? 'in',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? $product->cost_price,
                    'batch_no' => $item['batch_no'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            activity('stock')->performedOn($adjustment)->withProperties(['reason' => $reason->value, 'items' => count($items)])->log('Stock adjustment created');

            // Managers' own adjustments are approved immediately.
            if ($user->can('stock.adjust.approve')) {
                $this->approve($adjustment, $user);
            }

            return $adjustment->fresh('items');
        });
    }

    public function approve(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $approver) {
            $adjustment = StockAdjustment::withoutGlobalScopes()->lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->status !== 'pending') {
                throw new BusinessRuleException(__('Only pending adjustments can be approved.'));
            }

            foreach ($adjustment->items()->with('product')->get() as $item) {
                $type = $adjustment->reason === AdjustmentReason::Opening
                    ? MovementType::Opening
                    : ($item->direction === 'in' ? MovementType::AdjustmentIn : MovementType::AdjustmentOut);

                if ($item->direction === 'in') {
                    $this->stock->receive($adjustment->branch_id, $item->product, $item->quantity, $type, $adjustment, $item->unit_cost,
                        $item->batch_no, $item->expiry_date?->toDateString(), $adjustment->reason->label());
                } else {
                    $this->stock->issue($adjustment->branch_id, $item->product, $item->quantity, $type, $adjustment, true, $adjustment->reason->label());
                }
            }

            $adjustment->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
            activity('stock')->causedBy($approver)->performedOn($adjustment)->log('Stock adjustment approved');

            return $adjustment;
        });
    }

    public function reject(StockAdjustment $adjustment, User $approver, string $reason): StockAdjustment
    {
        if ($adjustment->status !== 'pending') {
            throw new BusinessRuleException(__('Only pending adjustments can be rejected.'));
        }
        $adjustment->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now(), 'rejection_reason' => $reason]);
        activity('stock')->causedBy($approver)->performedOn($adjustment)->withProperties(['reason' => $reason])->log('Stock adjustment rejected');

        return $adjustment;
    }
}
