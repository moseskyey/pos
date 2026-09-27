<?php

namespace App\Livewire\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\PicksProducts;
use App\Models\StockTake;
use App\Services\StockTakeService;
use Livewire\Component;
use Livewire\WithPagination;

class StockTakeCounter extends Component
{
    use PicksProducts, WithPagination;

    public StockTake $take;

    public string $filter = 'all';

    public string $search = '';

    public bool $addMode = true;

    public array $counts = [];

    public function mount(StockTake $take): void
    {
        $this->take = $take;
    }

    /** Scanning adds 1 (add mode) to the counted quantity. */
    public function addProduct(int $productId, mixed $quantity = null): void
    {
        abort_unless(auth()->user()->can('stock.take'), 403);
        try {
            $item = app(StockTakeService::class)->count($this->take, $productId, $quantity ?? 1, auth()->user(), $this->addMode);
            $this->dispatch('toast', message: __(':p counted: :q', ['p' => $item->product->name, 'q' => qty($item->counted_quantity)]), type: 'success');
            $this->dispatch('scan-ok');
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function saveCount(int $itemId): void
    {
        abort_unless(auth()->user()->can('stock.take'), 403);
        $value = $this->counts[$itemId] ?? null;
        if ($value === null || $value === '' || ! is_numeric($value) || $value < 0) {
            return;
        }
        $item = $this->take->items()->findOrFail($itemId);
        app(StockTakeService::class)->count($this->take, $item->product_id, $value, auth()->user());
        unset($this->counts[$itemId]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $items = $this->take->items()->with('product.unit')
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($p) => $p->search($this->search)))
            ->when($this->filter === 'uncounted', fn ($q) => $q->whereNull('counted_quantity'))
            ->when($this->filter === 'variance', fn ($q) => $q->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity'))
            ->join('products', 'products.id', '=', 'stock_take_items.product_id')
            ->orderBy('products.name')->select('stock_take_items.*')
            ->paginate(25);

        $all = $this->take->items();
        $stats = [
            'total' => (clone $all)->count(),
            'counted' => (clone $all)->whereNotNull('counted_quantity')->count(),
            'variance' => (clone $all)->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity')->count(),
            'value' => (clone $all)->whereNotNull('counted_quantity')->selectRaw('COALESCE(SUM((counted_quantity - expected_quantity) * unit_cost), 0) as v')->value('v'),
        ];

        return view('livewire.inventory.stock-take-counter', ['items' => $items, 'stats' => $stats, 'canCost' => auth()->user()->can('products.view_cost')]);
    }
}
