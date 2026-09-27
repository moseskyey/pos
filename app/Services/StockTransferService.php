<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Transfers: request → approve → dispatch (stock out) → receive (stock in).
 */
class StockTransferService
{
    public function __construct(protected StockService $stock, protected DocumentNumberService $numbers) {}

    /** @param array<int, array{product_id:int, quantity:mixed, note?:?string}> $items */
    public function request(int $fromBranchId, int $toBranchId, array $items, User $user, ?string $note = null): StockTransfer
    {
        if ($fromBranchId === $toBranchId) {
            throw new BusinessRuleException(__('Choose two different branches.'));
        }

        return DB::transaction(function () use ($fromBranchId, $toBranchId, $items, $user, $note) {
            $transfer = StockTransfer::withoutGlobalScopes()->create([
                'number' => $this->numbers->next('transfer', $fromBranchId),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => 'requested',
                'note' => $note,
                'requested_by' => $user->id,
            ]);
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $transfer->items()->create([
                    'product_id' => $product->id,
                    'quantity_requested' => $item['quantity'],
                    'unit_cost' => $product->cost_price,
                    'note' => $item['note'] ?? null,
                ]);
            }
            activity('stock')->performedOn($transfer)->log('Transfer requested');

            if ($user->can('stock.transfer.approve')) {
                $this->approve($transfer, $user);
            } else {
                app(AlertService::class)->notify($fromBranchId, 'stock.transfer.approve', new SystemAlert(
                    __('Transfer :n awaiting approval', ['n' => $transfer->number]),
                    __(':user requested :count items from :from to :to.', ['user' => $user->name, 'count' => count($items), 'from' => $transfer->fromBranch->name, 'to' => $transfer->toBranch->name]),
                    route('transfers.show', $transfer), 'bi-truck', 'info',
                ), $user);
            }

            return $transfer->fresh('items');
        });
    }

    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        $this->expect($transfer, 'requested');
        $transfer->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        activity('stock')->causedBy($user)->performedOn($transfer)->log('Transfer approved');

        return $transfer;
    }

    /** @param array<int, mixed> $quantities item_id => dispatched quantity */
    public function dispatch(StockTransfer $transfer, User $user, array $quantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $quantities) {
            $transfer = StockTransfer::withoutGlobalScopes()->lockForUpdate()->findOrFail($transfer->id);
            $this->expect($transfer, 'approved');

            foreach ($transfer->items()->with('product')->get() as $item) {
                $qty = Qty::round($quantities[$item->id] ?? $item->quantity_requested);
                if (Qty::isNegative($qty)) {
                    throw new BusinessRuleException(__('Quantities cannot be negative.'));
                }
                $movements = $this->stock->issue($transfer->from_branch_id, $item->product, $qty, MovementType::TransferOut, $transfer, false, __('To :branch', ['branch' => $transfer->toBranch->name]));
                $cost = $movements->isNotEmpty() && Qty::isPositive($qty)
                    ? Money::div(Money::sum($movements, fn ($m) => Money::mul(Qty::abs($m->quantity), $m->unit_cost)), $qty)
                    : $item->unit_cost;
                $item->update(['quantity_dispatched' => $qty, 'unit_cost' => $cost]);
            }

            $transfer->update(['status' => 'dispatched', 'dispatched_by' => $user->id, 'dispatched_at' => now()]);
            activity('stock')->causedBy($user)->performedOn($transfer)->log('Transfer dispatched');

            return $transfer;
        });
    }

    /** @param array<int, mixed> $quantities item_id => received quantity */
    public function receive(StockTransfer $transfer, User $user, array $quantities = [], array $notes = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $quantities, $notes) {
            $transfer = StockTransfer::withoutGlobalScopes()->lockForUpdate()->findOrFail($transfer->id);
            $this->expect($transfer, 'dispatched');

            $discrepancies = [];
            foreach ($transfer->items()->with('product')->get() as $item) {
                $qty = Qty::round($quantities[$item->id] ?? $item->quantity_dispatched);
                if (Qty::isNegative($qty) || Qty::gt($qty, $item->quantity_dispatched)) {
                    throw new BusinessRuleException(__('Received quantity for :product must be between 0 and :max.', ['product' => $item->product->name, 'max' => qty($item->quantity_dispatched)]));
                }
                $this->stock->receive($transfer->to_branch_id, $item->product, $qty, MovementType::TransferIn, $transfer, $item->unit_cost,
                    'TRF-'.$transfer->id, null, __('From :branch', ['branch' => $transfer->fromBranch->name]));
                $item->update(['quantity_received' => $qty, 'note' => $notes[$item->id] ?? $item->note]);
                if (Qty::cmp($qty, $item->quantity_dispatched) !== 0) {
                    $discrepancies[] = ['product' => $item->product->name, 'dispatched' => $item->quantity_dispatched, 'received' => $qty];
                }
            }

            $transfer->update(['status' => 'received', 'received_by' => $user->id, 'received_at' => now()]);
            activity('stock')->causedBy($user)->performedOn($transfer)->withProperties(['discrepancies' => $discrepancies])->log('Transfer received');

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! in_array($transfer->status, ['requested', 'approved'], true)) {
            throw new BusinessRuleException(__('Dispatched transfers cannot be cancelled.'));
        }
        $transfer->update(['status' => 'cancelled']);
        activity('stock')->causedBy($user)->performedOn($transfer)->log('Transfer cancelled');

        return $transfer;
    }

    protected function expect(StockTransfer $transfer, string $status): void
    {
        if ($transfer->status !== $status) {
            throw new BusinessRuleException(__('This transfer is :status.', ['status' => __($transfer->status)]));
        }
    }
}
