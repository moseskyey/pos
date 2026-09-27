<?php

namespace App\Livewire\Inventory;

use App\Enums\AdjustmentReason;
use App\Http\Requests\StockAdjustmentRequest;
use App\Livewire\Concerns\PicksProducts;
use App\Models\Product;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Support\BranchContext;
use Livewire\Component;

class AdjustmentForm extends Component
{
    use PicksProducts;

    public string $reason = 'damaged';

    public string $note = '';

    public array $items = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('stock.adjust'), 403);
    }

    public function addProduct(int $productId, mixed $quantity = null): void
    {
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] === $productId && empty($item['batch_no'])) {
                $this->items[$i]['quantity'] = (float) $item['quantity'] + (float) ($quantity ?? 1);

                return;
            }
        }
        $product = Product::with('unit')->findOrFail($productId);
        $branchId = app(BranchContext::class)->currentId();
        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit?->short_name,
            'batched' => $product->track_batches,
            'on_hand' => $branchId ? (float) app(StockService::class)->available($branchId, $product->id) : null,
            'direction' => AdjustmentReason::from($this->reason)->direction() ?? 'in',
            'quantity' => $quantity ?? 1,
            'unit_cost' => auth()->user()->can('products.view_cost') ? (float) $product->cost_price : null,
            'batch_no' => '',
            'expiry_date' => '',
        ];
    }

    public function updatedReason(): void
    {
        $direction = AdjustmentReason::tryFrom($this->reason)?->direction();
        if ($direction) {
            foreach ($this->items as $i => $item) {
                $this->items[$i]['direction'] = $direction;
            }
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(StockAdjustmentService $service)
    {
        $branchId = app(BranchContext::class)->currentId();
        if (! $branchId) {
            $this->dispatch('toast', message: __('Select a branch in the navbar first.'), type: 'error');

            return null;
        }
        $data = $this->validate(StockAdjustmentRequest::rulesFor(), [], ['items' => __('products')]);
        $adjustment = $service->create($branchId, AdjustmentReason::from($data['reason']), $data['items'], auth()->user(), $data['note'] ?? null);

        session()->flash('success', $adjustment->status === 'approved' ? __('Adjustment :n posted to stock.', ['n' => $adjustment->number]) : __('Adjustment :n submitted for approval.', ['n' => $adjustment->number]));

        return $this->redirectRoute('adjustments.show', $adjustment);
    }

    public function render()
    {
        return view('livewire.inventory.adjustment-form', [
            'reasons' => AdjustmentReason::options(),
            'canCost' => auth()->user()->can('products.view_cost'),
            'branch' => app(BranchContext::class)->current(),
        ]);
    }
}
