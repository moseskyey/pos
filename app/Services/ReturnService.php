<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Customer returns / refunds against a completed sale.
 */
class ReturnService
{
    public const REFUND_METHODS = ['cash', 'mpesa', 'tigopesa', 'airtel', 'halopesa', 'bank', 'store_credit', 'account'];

    public function __construct(
        protected StockService $stock,
        protected DocumentNumberService $numbers,
        protected CustomerLedgerService $ledger,
        protected LoyaltyService $loyalty,
        protected ShiftService $shifts,
        protected ApprovalService $approvals,
    ) {}

    /**
     * @param  array<int, array{quantity: mixed, condition?: string}>  $items  keyed by sale_item_id
     * @param  array<string, int>  $approvals
     */
    public function process(Sale $sale, array $items, string $reason, string $refundMethod, User $user, ?string $reference = null, array $approvals = []): SaleReturn
    {
        if (! $user->can('sales.return')) {
            $approver = isset($approvals['return']) ? User::find($approvals['return']) : null;
            if (! $approver || ! $approver->can('sales.return')) {
                throw new ApprovalRequiredException('return', 'sales.return', __('Returns need manager approval.'));
            }
        }
        if (! in_array($refundMethod, self::REFUND_METHODS, true)) {
            throw new BusinessRuleException(__('Choose a refund method.'));
        }

        return DB::transaction(function () use ($sale, $items, $reason, $refundMethod, $user, $reference, $approvals) {
            $sale = Sale::withoutGlobalScopes()->with('items.product')->lockForUpdate()->findOrFail($sale->id);
            if (! in_array($sale->status, [SaleStatus::Completed], true)) {
                throw new BusinessRuleException(__('Only completed sales can be returned.'));
            }
            if (in_array($refundMethod, ['store_credit', 'account'], true) && ! $sale->customer_id) {
                throw new BusinessRuleException(__('This refund method needs a customer on the sale.'));
            }
            if (PaymentMethod::tryFrom($refundMethod)?->isMobileMoney() && ! $reference) {
                throw new BusinessRuleException(__('Enter the mobile money transaction reference.'));
            }

            $shift = null;
            if ($refundMethod === 'cash') {
                $shift = $this->shifts->current($user, $sale->branch_id);
                if (! $shift) {
                    throw new BusinessRuleException(__('Open a shift to refund cash from the drawer.'));
                }
            }

            $return = SaleReturn::withoutGlobalScopes()->create([
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'approved_by' => $approvals['return'] ?? null,
                'number' => $this->numbers->next('return', $sale->branch_id),
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'refund_reference' => $reference,
            ]);

            $subtotal = '0';
            $tax = '0';
            $cost = '0';
            $count = 0;
            foreach ($sale->items as $item) {
                $line = $items[$item->id] ?? null;
                $qty = Qty::round($line['quantity'] ?? 0);
                if (! Qty::isPositive($qty)) {
                    continue;
                }
                if (Qty::gt($qty, $item->returnableQuantity())) {
                    throw new BusinessRuleException(__('Only :q of :p can be returned.', ['q' => qty($item->returnableQuantity()), 'p' => $item->name]));
                }
                $condition = ($line['condition'] ?? 'restock') === 'damaged' ? 'damaged' : 'restock';
                $unitRefund = Money::div($item->netTotal(), $item->quantity, 4);
                $lineTotal = Money::mul($unitRefund, $qty);
                $lineTax = Money::mul(Money::div($item->tax_amount, $item->quantity, 4), $qty);

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_price' => Money::round($unitRefund),
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'cost_price' => $item->cost_price,
                    'condition' => $condition,
                ]);
                $item->update(['returned_quantity' => Qty::add($item->returned_quantity, $qty)]);

                $baseQty = Qty::mul($qty, $item->conversion_factor);
                if ($condition === 'restock' && $item->bundle_components) {
                    // Bundles go back as their components, in the quantities recorded at sale time.
                    foreach ($item->bundle_components as $component) {
                        $product = Product::withTrashed()->find($component['product_id']);
                        if ($product) {
                            $this->stock->receive($sale->branch_id, $product, Qty::mul($baseQty, $component['quantity']), MovementType::Return, $return,
                                $product->cost_price, null, null, $reason);
                        }
                    }
                } elseif ($condition === 'restock') {
                    $batchId = StockMovement::withoutGlobalScopes()->where('reference_type', $sale->getMorphClass())->where('reference_id', $sale->id)
                        ->where('product_id', $item->product_id)->whereNotNull('batch_id')->orderByDesc('id')->value('batch_id');
                    $this->stock->receive($sale->branch_id, $item->product, $baseQty, MovementType::Return, $return,
                        Money::div($item->cost_price, $item->conversion_factor), null, null, $reason, $batchId);
                }

                $subtotal = Money::add($subtotal, $lineTotal);
                $tax = Money::add($tax, $lineTax);
                $cost = Money::add($cost, Money::mul($item->cost_price, $qty));
                $count++;
            }

            if ($count === 0) {
                throw new BusinessRuleException(__('Select at least one item to return.'));
            }

            $return->update(['subtotal' => $subtotal, 'tax_total' => $tax, 'refund_total' => $subtotal, 'cost_total' => $cost]);

            $customer = $sale->customer_id ? Customer::withTrashed()->find($sale->customer_id) : null;
            if ($refundMethod === 'store_credit') {
                $this->ledger->post($customer, 'return', 0, $subtotal, $return, __('Return :n', ['n' => $return->number]), 'store_credit');
            } elseif ($refundMethod === 'account') {
                $this->ledger->post($customer, 'return', 0, $subtotal, $return, __('Return :n', ['n' => $return->number]));
                // Reduce what is outstanding on the original invoice.
                $reduce = Money::min($sale->balance_due, $subtotal);
                if (Money::isPositive($reduce)) {
                    $sale->update(['balance_due' => Money::sub($sale->balance_due, $reduce)]);
                }
            }

            if ($customer && $sale->loyalty_earned > 0 && Money::isPositive($sale->total)) {
                $points = (int) floor($sale->loyalty_earned * (float) Money::div($subtotal, $sale->total, 6));
                if ($points > 0) {
                    $this->loyalty->reverse($sale, $points);
                }
            }

            if (isset($approvals['return']) && ($approver = User::find($approvals['return']))) {
                $this->approvals->record('return', $user, $approver, $return, $subtotal, $reason);
            }
            activity('sales')->causedBy($user)->performedOn($return)->withProperties(['sale' => $sale->number, 'refund' => $subtotal, 'method' => $refundMethod])->log('Return processed');

            return $return->fresh('items');
        });
    }
}
