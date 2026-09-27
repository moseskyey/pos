<?php

namespace App\Livewire\Tables;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable extends DataTable
{
    protected string $defaultSort = 'name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Products');
    }

    protected function query(): Builder
    {
        $ids = app(BranchContext::class)->activeIds();

        return Product::query()
            ->with(['category.parent', 'brand', 'unit', 'barcodes'])
            ->withCount('variants')
            ->when(class_exists(ProductStock::class), fn ($q) => $q->withSum(['stocks as stock_qty' => fn ($s) => $s->withoutGlobalScopes()->whereIn('branch_id', $ids)], 'quantity'))
            ->whereNull('parent_id');
    }

    protected function searchable(): array
    {
        return ['name', 'sku', 'barcodes.barcode', 'variants.sku'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('category_id', __('Categories'), Category::options(false))
                ->query(fn ($q, $v) => $q->whereIn('category_id', Category::find($v)?->descendantIds() ?? [$v])),
            Filter::select('brand_id', __('Brands'), Brand::query()->orderBy('name')->pluck('name', 'id')->all()),
            Filter::select('stock', __('Stock'), ['low' => __('Low stock'), 'out' => __('Out of stock'), 'in' => __('In stock')])
                ->query(function ($q, $v) {
                    $ids = app(BranchContext::class)->activeIds();
                    $sum = '(select coalesce(sum(quantity),0) from product_stocks where product_stocks.product_id = products.id and branch_id in ('.implode(',', $ids ?: [0]).'))';
                    $q->where('track_stock', true);
                    match ($v) {
                        'out' => $q->whereRaw("$sum <= 0"),
                        'low' => $q->whereRaw("$sum > 0 and $sum <= products.reorder_level"),
                        default => $q->whereRaw("$sum > 0"),
                    };
                }),
            Filter::boolean('is_active', __('Active')),
        ];
    }

    protected function columns(): array
    {
        $canCost = auth()->user()->can('products.view_cost');

        return [
            Column::make(__('Product'), 'name')->sortable()->view('products.partials.name-cell')->exportAs(fn ($p) => $p->name),
            Column::make(__('SKU'), 'sku')->sortable()->html(fn ($p) => '<span class="font-monospace small text-nowrap">'.e($p->sku).'</span>')->exportAs(fn ($p) => $p->sku),
            Column::make(__('Barcode'))->exportOnly()->exportAs(fn ($p) => $p->primaryBarcode()),
            Column::make(__('Category'))->format(fn ($p) => $p->category?->fullName() ?? '—'),
            Column::make(__('Brand'))->format(fn ($p) => $p->brand?->name ?? '—'),
            Column::make(__('Cost'), 'cost_price')->sortable()->money()->visible($canCost),
            Column::make(__('Price'), 'retail_price')->sortable()->money()->html(fn ($p) => $p->has_variants
                ? '<span class="text-body-secondary small">'.e(__('Variants')).'</span>'
                : '<span class="fw-semibold">'.e(money($p->retail_price)).'</span>'),
            Column::make(__('Stock'), 'stock_qty')->sortable()->number()->view('products.partials.stock-cell')
                ->visible(class_exists(ProductStock::class))->exportAs(fn ($p) => (float) $p->stock_qty),
            Column::make(__('Status'), 'is_active')->boolean(),
        ];
    }

    protected function bulkActions(): array
    {
        $user = auth()->user();
        $actions = [];
        if ($user->can('products.edit')) {
            $actions['bulkActivate'] = __('Activate');
            $actions['bulkDeactivate'] = __('Deactivate');
        }
        if ($user->can('products.labels')) {
            $actions['bulkLabels'] = __('Print barcode labels');
        }

        return $actions;
    }

    public function bulkActivate(array $ids): int
    {
        abort_unless(auth()->user()->can('products.edit'), 403);

        return Product::whereIn('id', $ids)->get()->each->update(['is_active' => true])->count();
    }

    public function bulkDeactivate(array $ids): int
    {
        abort_unless(auth()->user()->can('products.edit'), 403);

        return Product::whereIn('id', $ids)->get()->each->update(['is_active' => false])->count();
    }

    public function bulkLabels(array $ids): int
    {
        $this->redirectRoute('labels.index', ['products' => implode(',', $ids)]);

        return count($ids);
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('products.show', $row);
    }

    protected function rowActions(mixed $row): ?string
    {
        if (! auth()->user()->can('products.edit')) {
            return null;
        }

        return '<a href="'.route('products.edit', $row).'" class="btn btn-sm btn-light btn-icon" title="'.e(__('Edit')).'"><i class="bi bi-pencil"></i></a>';
    }

    protected function emptyState(): array
    {
        return [
            'icon' => 'bi-box-seam', 'title' => __('No products yet'),
            'message' => __('Add products one by one or import them from Excel.'),
            'action' => auth()->user()->can('products.create') ? route('products.create') : null, 'actionLabel' => __('Add product'),
        ];
    }
}
