<?php

namespace App\Livewire\Purchases;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Concerns\PicksProducts;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Support\BranchContext;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Line-item editor for purchase orders ("order"), goods received notes
 * ("receipt") and returns to supplier ("return").
 */
class DocumentForm extends Component
{
    use PicksProducts;

    #[Locked]
    public string $mode = 'order';

    #[Locked]
    public ?int $orderId = null;

    #[Locked]
    public ?int $receiptId = null;

    public ?int $supplierId = null;

    public string $date = '';

    public ?string $expectedDate = null;

    public string $invoiceNo = '';

    public string $note = '';

    public string $reason = '';

    public array $items = [];

    public function mount(string $mode = 'order', ?int $order = null, ?int $receipt = null, ?int $supplier = null, array $prefill = []): void
    {
        $this->mode = $mode;
        abort_unless(auth()->user()->can(match ($mode) {
            'receipt' => 'purchases.receive', 'return' => 'purchases.return', default => 'purchases.manage'
        }), 403);
        $this->date = now()->toDateString();
        $this->supplierId = $supplier;

        if ($order && ($po = PurchaseOrder::with('items.product.unit')->find($order))) {
            $this->orderId = $po->id;
            $this->supplierId = $po->supplier_id;
            $this->note = $mode === 'order' ? (string) $po->note : '';
            $this->expectedDate = $po->expected_date?->toDateString();
            foreach ($po->items as $item) {
                $qty = $mode === 'receipt' ? (float) $item->outstanding() : (float) $item->quantity;
                if ($mode === 'receipt' && $qty <= 0) {
                    continue;
                }
                $this->items[] = $this->row($item->product, $qty, (float) $item->unit_cost) + ['purchase_order_item_id' => $item->id, 'ordered' => (float) $item->quantity, 'received' => (float) $item->received_quantity];
            }
        }

        if ($receipt && ($grn = GoodsReceipt::with('items.product.unit')->find($receipt))) {
            $this->receiptId = $grn->id;
            $this->supplierId = $grn->supplier_id;
            foreach ($grn->items as $item) {
                $this->items[] = $this->row($item->product, 0, (float) $item->unit_cost) + ['goods_receipt_item_id' => $item->id, 'returnable' => (float) $item->returnable()];
            }
        }

        foreach ($prefill as $line) {
            if ($product = Product::with('unit')->find($line['product_id'])) {
                $this->items[] = $this->row($product, (float) $line['quantity'], (float) ($line['unit_cost'] ?? $this->knownCost($product)));
            }
        }
    }

    protected function row(Product $product, float $qty, float $cost): array
    {
        return [
            'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit?->short_name,
            'batched' => $product->track_batches, 'quantity' => $qty, 'unit_cost' => $cost, 'tax_rate' => (float) $product->taxRate(),
            'batch_no' => '', 'expiry_date' => '',
        ];
    }

    public function addProduct(int $productId, mixed $quantity = null): void
    {
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] === $productId && empty($item['purchase_order_item_id']) && empty($item['goods_receipt_item_id'])) {
                $this->items[$i]['quantity'] = (float) $item['quantity'] + (float) ($quantity ?? 1);

                return;
            }
        }
        $product = Product::with('unit')->findOrFail($productId);
        $this->items[] = $this->row($product, (float) ($quantity ?? 1), $this->knownCost($product));
    }

    /** Current cost as a starting value, only for users allowed to see costs. */
    protected function knownCost(Product $product): float
    {
        return auth()->user()->can('products.view_cost') ? (float) $product->cost_price : 0.0;
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function save(PurchaseService $service)
    {
        $rules = [
            'supplierId' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
        if ($this->mode === 'receipt') {
            $rules['items.*.expiry_date'] = ['nullable', 'date'];
            $rules['date'] = ['required', 'date', 'before_or_equal:today'];
        }
        if ($this->mode === 'return') {
            $rules['reason'] = ['required', 'string', 'max:255'];
        }
        $this->validate($rules, [], ['supplierId' => __('supplier'), 'items' => __('products')]);

        $branchId = app(BranchContext::class)->currentId();
        if (! $branchId) {
            $this->dispatch('toast', message: __('Select a single branch in the navbar first.'), type: 'error');

            return null;
        }
        $supplier = Supplier::findOrFail($this->supplierId);
        $user = auth()->user();

        try {
            if ($this->mode === 'order') {
                $doc = $service->saveOrder($branchId, $supplier, $this->items, $user, ['expected_date' => $this->expectedDate ?: null, 'note' => $this->note ?: null], $this->orderId ? PurchaseOrder::find($this->orderId) : null);
                session()->flash('success', __('Purchase order :n saved.', ['n' => $doc->number]));

                return $this->redirectRoute('purchase-orders.show', $doc);
            }
            if ($this->mode === 'receipt') {
                $doc = $service->receive($branchId, $supplier, $this->items, $user, ['supplier_invoice_no' => $this->invoiceNo ?: null, 'received_at' => $this->date, 'note' => $this->note ?: null], $this->orderId ? PurchaseOrder::find($this->orderId) : null);
                session()->flash('success', __('Goods received (:n). Stock and costs updated.', ['n' => $doc->number]));

                return $this->redirectRoute('goods-receipts.show', $doc);
            }
            $doc = $service->returnToSupplier($branchId, $supplier, $this->items, $this->reason, $user, $this->receiptId ? GoodsReceipt::find($this->receiptId) : null);
            session()->flash('success', __('Return :n recorded.', ['n' => $doc->number]));

            return $this->redirectRoute('purchase-returns.show', $doc);
        } catch (BusinessRuleException|InsufficientStockException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return null;
        }
    }

    public function render()
    {
        $subtotal = collect($this->items)->sum(fn ($i) => (float) $i['quantity'] * (float) $i['unit_cost']);
        $tax = collect($this->items)->sum(fn ($i) => (float) $i['quantity'] * (float) $i['unit_cost'] * (float) $i['tax_rate'] / 100);

        return view('livewire.purchases.document-form', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'subtotal' => Money::round($subtotal),
            'tax' => Money::round($tax),
            'total' => Money::round($subtotal + $tax),
            'locked' => (bool) ($this->orderId && $this->mode === 'receipt') || (bool) $this->receiptId,
        ]);
    }
}
