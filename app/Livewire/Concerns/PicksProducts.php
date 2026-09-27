<?php

namespace App\Livewire\Concerns;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Product search-as-you-type with barcode "enter to add" support.
 */
trait PicksProducts
{
    public string $productSearch = '';

    abstract public function addProduct(int $productId, mixed $quantity = null): void;

    #[Computed]
    public function productResults(): Collection
    {
        $term = trim($this->productSearch);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Product::query()->active()->sellable()->search($term)->with('unit')->orderBy('name')->limit(12)->get();
    }

    /** Enter in the search box: exact barcode/SKU match or the single result. */
    public function pickFirst(): void
    {
        $term = trim($this->productSearch);
        if ($term === '') {
            return;
        }
        $match = app(ProductService::class)->findByBarcode($term);
        if ($match) {
            $this->addProduct($match['product']->id, $match['quantity'] ?? null);
        } elseif ($this->productResults->count() === 1) {
            $this->addProduct($this->productResults->first()->id);
        } else {
            return;
        }
        $this->productSearch = '';
        unset($this->productResults);
    }

    public function pickProduct(int $id): void
    {
        $this->addProduct($id);
        $this->productSearch = '';
        unset($this->productResults);
    }
}
