<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Support\Money;
use App\Support\Qty;
use Illuminate\Support\Facades\DB;

/**
 * Purchase orders, goods received notes (GRN) with moving-average costing,
 * supplier bills and purchase returns.
 */
class PurchaseService
{
    public function __construct(
        protected StockService $stock,
        protected DocumentNumberService $numbers,
        protected SupplierLedgerService $ledger,
        protected ProductService $products,
    ) {}

    /** @param array<int, array{product_id:int, quantity:mixed, unit_cost:mixed}> $items */
    public function saveOrder(int $branchId, Supplier $supplier, array $items, User $user, array $attributes = [], ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($branchId, $supplier, $items, $user, $attributes, $order) {
            if ($order && $order->status !== 'draft') {
                throw new BusinessRuleException(__('Only draft purchase orders can be edited.'));
            }
            $lines = $this->priceItems($items);
            $data = [
                'supplier_id' => $supplier->id,
                'order_date' => $attributes['order_date'] ?? now()->toDateString(),
                'expected_date' => $attributes['expected_date'] ?? null,
                'note' => $attributes['note'] ?? null,
                'subtotal' => Money::sum($lines, 'line_total'),
                'tax_total' => Money::sum($lines, 'tax_amount'),
            ];
            $data['total'] = Money::add($data['subtotal'], $data['tax_total']);

            if ($order) {
                $order->update($data);
                $order->items()->delete();
            } else {
                $order = PurchaseOrder::withoutGlobalScopes()->create($data + [
                    'branch_id' => $branchId, 'number' => $this->numbers->next('purchase_order', $branchId),
                    'status' => 'draft', 'created_by' => $user->id,
                ]);
            }
            foreach ($lines as $line) {
                $order->items()->create($line);
            }
            activity('purchases')->causedBy($user)->performedOn($order)->log('Purchase order saved');

            return $order->fresh('items');
        });
    }

    public function markSent(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'sent'], true)) {
            throw new BusinessRuleException(__('This purchase order cannot be sent.'));
        }
        $order->update(['status' => 'sent', 'sent_at' => now()]);
        activity('purchases')->causedBy($user)->performedOn($order)->log('Purchase order sent');

        return $order;
    }

    public function cancelOrder(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if (in_array($order->status, ['received', 'cancelled'], true) || $order->items()->where('received_quantity', '>', 0)->exists()) {
            throw new BusinessRuleException(__('Orders with received goods cannot be cancelled.'));
        }
        $order->update(['status' => 'cancelled']);
        activity('purchases')->causedBy($user)->performedOn($order)->log('Purchase order cancelled');

        return $order;
    }

    /**
     * Receive goods (GRN) directly or against a purchase order.
     *
     * @param  array<int, array{product_id:int, quantity:mixed, unit_cost:mixed, batch_no?:?string, expiry_date?:?string, purchase_order_item_id?:?int}>  $items
     */
    public function receive(int $branchId, Supplier $supplier, array $items, User $user, array $attributes = [], ?PurchaseOrder $order = null): GoodsReceipt
    {
        return DB::transaction(function () use ($branchId, $supplier, $items, $user, $attributes, $order) {
            if ($order) {
                $order = PurchaseOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
                if (! $order->isReceivable()) {
                    throw new BusinessRuleException(__('This purchase order is :s.', ['s' => __($order->status)]));
                }
            }
            $items = array_values(array_filter($items, fn ($i) => Qty::isPositive($i['quantity'] ?? 0)));
            if (! $items) {
                throw new BusinessRuleException(__('Enter at least one received quantity.'));
            }
            $lines = $this->priceItems($items);

            $receipt = GoodsReceipt::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $order?->id,
                'number' => $this->numbers->next('goods_receipt', $branchId),
                'supplier_invoice_no' => $attributes['supplier_invoice_no'] ?? null,
                'received_at' => $attributes['received_at'] ?? now()->toDateString(),
                'subtotal' => Money::sum($lines, 'line_total'),
                'tax_total' => Money::sum($lines, 'tax_amount'),
                'note' => $attributes['note'] ?? null,
                'user_id' => $user->id,
            ]);
            $receipt->update(['total' => Money::add($receipt->subtotal, $receipt->tax_total)]);

            foreach ($lines as $i => $line) {
                $product = Product::findOrFail($line['product_id']);
                $source = $items[$i];
                $receipt->items()->create($line + [
                    'purchase_order_item_id' => $source['purchase_order_item_id'] ?? null,
                    'batch_no' => $source['batch_no'] ?? null,
                    'expiry_date' => $source['expiry_date'] ?? null,
                ]);

                $this->updateCost($product, $line['quantity'], $line['unit_cost'], $user, $receipt->number);
                $this->stock->receive($branchId, $product, $line['quantity'], MovementType::Purchase, $receipt, $line['unit_cost'],
                    ($source['batch_no'] ?? null) ?: ($product->track_batches ? $receipt->number : null), $source['expiry_date'] ?? null, $supplier->name);

                if (! empty($source['purchase_order_item_id']) && $order) {
                    $poItem = $order->items()->find($source['purchase_order_item_id']);
                    $poItem?->update(['received_quantity' => Qty::add($poItem->received_quantity, $line['quantity'])]);
                }
            }

            if ($order) {
                $order->load('items');
                $complete = $order->items->every(fn ($i) => Qty::gte($i->received_quantity, $i->quantity));
                $order->update(['status' => $complete ? 'received' : 'partially_received']);
            }

            // Supplier bill (accounts payable).
            $bill = SupplierBill::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'goods_receipt_id' => $receipt->id,
                'bill_no' => $receipt->supplier_invoice_no,
                'bill_date' => $receipt->received_at,
                'due_date' => $receipt->received_at->copy()->addDays($supplier->payment_terms_days),
                'subtotal' => $receipt->subtotal,
                'tax_total' => $receipt->tax_total,
                'total' => $receipt->total,
                'status' => 'unpaid',
                'user_id' => $user->id,
            ]);
            $this->ledger->post($supplier, 'bill', 0, $bill->total, $receipt, __('GRN :n', ['n' => $receipt->number]), $branchId);
            activity('purchases')->causedBy($user)->performedOn($receipt)->withProperties(['total' => $receipt->total])->log('Goods received');

            return $receipt->fresh('items');
        });
    }

    /** Moving average (or last cost) across all branches' stock. */
    public function updateCost(Product $product, string $quantity, string $unitCost, ?User $user, ?string $reference = null): void
    {
        if (setting('inventory.costing', 'average') === 'last') {
            $newCost = Money::round($unitCost);
        } else {
            $onHand = Qty::max((string) ProductStock::withoutGlobalScopes()->where('product_id', $product->id)->sum('quantity'), 0);
            $totalQty = Qty::add($onHand, $quantity);
            $newCost = Qty::isPositive($totalQty)
                ? Money::div(Money::add(Money::mul($onHand, $product->cost_price), Money::mul($quantity, $unitCost)), $totalQty)
                : Money::round($unitCost);
        }
        if (Money::cmp($newCost, $product->cost_price) !== 0) {
            $this->products->changePrices($product, ['cost_price' => $newCost], $user, __('GRN :r', ['r' => $reference]));
        }
    }

    /** Manual bill (services, transport…) not linked to a GRN. */
    public function recordBill(int $branchId, Supplier $supplier, array $data, User $user): SupplierBill
    {
        return DB::transaction(function () use ($branchId, $supplier, $data, $user) {
            $bill = SupplierBill::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'bill_no' => $data['bill_no'] ?? null,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? now()->parse($data['bill_date'])->addDays($supplier->payment_terms_days),
                'subtotal' => Money::sub($data['total'], $data['tax_total'] ?? 0),
                'tax_total' => Money::round($data['tax_total'] ?? 0),
                'total' => Money::round($data['total']),
                'status' => 'unpaid',
                'description' => $data['description'] ?? null,
                'user_id' => $user->id,
            ]);
            $this->ledger->post($supplier, 'bill', 0, $bill->total, $bill, __('Bill :n', ['n' => $bill->bill_no ?? $bill->id]), $branchId);

            return $bill;
        });
    }

    /**
     * Return goods to a supplier.
     *
     * @param  array<int, array{product_id:int, quantity:mixed, unit_cost:mixed, goods_receipt_item_id?:?int}>  $items
     */
    public function returnToSupplier(int $branchId, Supplier $supplier, array $items, string $reason, User $user, ?GoodsReceipt $receipt = null): PurchaseReturn
    {
        return DB::transaction(function () use ($branchId, $supplier, $items, $reason, $user, $receipt) {
            $items = array_values(array_filter($items, fn ($i) => Qty::isPositive($i['quantity'] ?? 0)));
            if (! $items) {
                throw new BusinessRuleException(__('Enter at least one quantity to return.'));
            }
            $lines = $this->priceItems($items);
            $return = PurchaseReturn::withoutGlobalScopes()->create([
                'branch_id' => $branchId, 'supplier_id' => $supplier->id, 'goods_receipt_id' => $receipt?->id,
                'number' => $this->numbers->next('purchase_return', $branchId), 'reason' => $reason,
                'subtotal' => Money::sum($lines, 'line_total'), 'tax_total' => Money::sum($lines, 'tax_amount'), 'user_id' => $user->id,
            ]);
            $return->update(['total' => Money::add($return->subtotal, $return->tax_total)]);

            foreach ($lines as $i => $line) {
                $grnItemId = $items[$i]['goods_receipt_item_id'] ?? null;
                if ($grnItemId) {
                    $grnItem = GoodsReceiptItem::findOrFail($grnItemId);
                    if (Qty::gt($line['quantity'], $grnItem->returnable())) {
                        throw new BusinessRuleException(__('Only :q of :p can be returned.', ['q' => qty($grnItem->returnable()), 'p' => $grnItem->product->name]));
                    }
                    $grnItem->update(['returned_quantity' => Qty::add($grnItem->returned_quantity, $line['quantity'])]);
                }
                $return->items()->create([
                    'goods_receipt_item_id' => $grnItemId, 'product_id' => $line['product_id'], 'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'], 'tax_amount' => $line['tax_amount'], 'line_total' => $line['line_total'],
                ]);
                $this->stock->issue($branchId, Product::findOrFail($line['product_id']), $line['quantity'], MovementType::PurchaseReturn, $return, false, $reason, $line['unit_cost']);
            }

            $this->ledger->post($supplier, 'return', $return->total, 0, $return, __('Return :n', ['n' => $return->number]), $branchId);
            activity('purchases')->causedBy($user)->performedOn($return)->log('Goods returned to supplier');

            return $return->fresh('items');
        });
    }

    /** Price lines: unit cost is VAT-exclusive; input VAT = rate × line. */
    protected function priceItems(array $items): array
    {
        $products = Product::query()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']) ?? throw new BusinessRuleException(__('Unknown product.'));
            $qty = Qty::round($item['quantity']);
            $cost = Money::round($item['unit_cost'] ?? $product->cost_price);
            $lineTotal = Money::mul($qty, $cost);
            $rate = $product->taxRate();
            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'tax_rate' => $rate,
                'tax_amount' => Money::percent($lineTotal, $rate),
                'line_total' => $lineTotal,
            ];
        }

        return $lines;
    }
}
