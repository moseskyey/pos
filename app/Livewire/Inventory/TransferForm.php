<?php

namespace App\Livewire\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\StockTransferRequest;
use App\Livewire\Concerns\PicksProducts;
use App\Models\Branch;
use App\Models\Product;
use App\Services\StockService;
use App\Services\StockTransferService;
use App\Support\BranchContext;
use Livewire\Component;

class TransferForm extends Component
{
    use PicksProducts;

    public ?int $from_branch_id = null;

    public ?int $to_branch_id = null;

    public string $note = '';

    public array $items = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('stock.transfer'), 403);
        $this->from_branch_id = app(BranchContext::class)->currentId();
    }

    public function addProduct(int $productId, mixed $quantity = null): void
    {
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] === $productId) {
                $this->items[$i]['quantity'] = (float) $item['quantity'] + (float) ($quantity ?? 1);

                return;
            }
        }
        $product = Product::with('unit')->findOrFail($productId);
        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit?->short_name,
            'quantity' => $quantity ?? 1,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(StockTransferService $service)
    {
        $data = $this->validate(StockTransferRequest::rulesFor());
        abort_unless(app(BranchContext::class)->canAccess((int) $data['from_branch_id']) || auth()->user()->can('branches.view_all'), 403);

        try {
            $transfer = $service->request((int) $data['from_branch_id'], (int) $data['to_branch_id'], $data['items'], auth()->user(), $data['note'] ?? null);
        } catch (BusinessRuleException $e) {
            $this->addError('to_branch_id', $e->getMessage());

            return null;
        }

        session()->flash('success', __('Transfer :n created.', ['n' => $transfer->number]));

        return $this->redirectRoute('transfers.show', $transfer);
    }

    public function render()
    {
        $available = $this->from_branch_id && $this->items
            ? app(StockService::class)->availableMany($this->from_branch_id, array_column($this->items, 'product_id'))
            : [];

        return view('livewire.inventory.transfer-form', [
            'fromBranches' => app(BranchContext::class)->accessibleBranches()->pluck('name', 'id'),
            'toBranches' => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'available' => $available,
        ]);
    }
}
