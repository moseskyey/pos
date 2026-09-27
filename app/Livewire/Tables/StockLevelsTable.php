<?php

namespace App\Livewire\Tables;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ProductStock;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockLevelsTable extends DataTable
{
    protected string $defaultSort = 'products.name';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Stock levels');
    }

    protected function query(): Builder
    {
        return ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.track_stock', true)
            ->select('product_stocks.*', 'products.name as product_name', 'products.sku', 'products.cost_price', 'products.retail_price', 'products.reorder_level')
            ->selectRaw('product_stocks.quantity * products.cost_price as cost_value, product_stocks.quantity * products.retail_price as retail_value')
            ->with(['product.unit', 'branch']);
    }

    protected function searchable(): array
    {
        return ['product.name', 'product.sku'];
    }

    protected function filterDefinitions(): array
    {
        $filters = [
            Filter::select('status', __('Stock status'), ['low' => __('Low stock'), 'out' => __('Out of stock'), 'over' => __('Overstock (> 5× reorder)'), 'in' => __('In stock')])
                ->query(fn ($q, $v) => match ($v) {
                    'low' => $q->where('product_stocks.quantity', '>', 0)->whereColumn('product_stocks.quantity', '<=', 'products.reorder_level'),
                    'out' => $q->where('product_stocks.quantity', '<=', 0),
                    'over' => $q->where('products.reorder_level', '>', 0)->whereRaw('product_stocks.quantity > products.reorder_level * 5'),
                    default => $q->where('product_stocks.quantity', '>', 0),
                }),
            Filter::select('category_id', __('Categories'), Category::options(false))
                ->query(fn ($q, $v) => $q->whereIn('products.category_id', Category::find($v)?->descendantIds() ?? [$v])),
        ];
        if (count(app(BranchContext::class)->activeIds()) > 1) {
            $filters[] = Filter::select('branch_id', __('Branches'), Branch::query()->whereIn('id', app(BranchContext::class)->activeIds())->pluck('name', 'id')->all())
                ->query(fn ($q, $v) => $q->where('product_stocks.branch_id', $v));
        }

        return $filters;
    }

    protected function columns(): array
    {
        $value = auth()->user()->can('stock.value.view');
        $cost = $value && auth()->user()->can('products.view_cost');

        return [
            Column::make(__('Product'), 'product_name')->sortable('products.name')->html(fn ($s) => '<div class="fw-semibold">'.e($s->product_name).'</div><div class="small text-body-secondary font-monospace">'.e($s->sku).'</div>')->exportAs(fn ($s) => $s->product_name),
            Column::make(__('SKU'), 'sku')->exportOnly(),
            Column::make(__('Branch'))->format(fn ($s) => $s->branch?->name)->visible(count(app(BranchContext::class)->activeIds()) > 1),
            Column::make(__('On hand'), 'quantity')->sortable('product_stocks.quantity')->number()->html(function ($s) {
                $q = (float) $s->quantity;
                $unit = e($s->product?->unit?->short_name);
                $cls = $q <= 0 ? 'text-bg-danger-soft' : ($q <= (float) $s->reorder_level ? 'text-bg-warning-soft' : null);

                return $cls ? '<span class="badge rounded-pill '.$cls.'">'.e(qty($q)).' '.$unit.'</span>' : '<span class="fw-semibold">'.e(qty($q)).'</span> <span class="small text-body-secondary">'.$unit.'</span>';
            }),
            Column::make(__('Reorder at'), 'reorder_level')->number(),
            Column::make(__('Value (cost)'), 'cost_value')->sortable('cost_value')->money()->visible($cost)->total(),
            Column::make(__('Value (retail)'), 'retail_value')->sortable('retail_value')->money()->visible($value)->total(),
        ];
    }

    protected function totals(): array
    {
        $totals = [];
        $q = (clone $this->filteredQuery())->reorder();
        $sumCost = (clone $q)->toBase()->select(DB::raw('SUM(product_stocks.quantity * products.cost_price) as c, SUM(product_stocks.quantity * products.retail_price) as r'))->first();
        foreach (collect($this->columns())->filter(fn ($c) => $c->visible && ! $c->onlyExport)->values() as $i => $column) {
            if ($column->field === 'cost_value') {
                $totals[$i] = money(Money::round($sumCost->c ?? 0));
            }
            if ($column->field === 'retail_value') {
                $totals[$i] = money(Money::round($sumCost->r ?? 0));
            }
        }

        return $totals;
    }

    protected function rowUrl(mixed $row): ?string
    {
        return route('products.show', $row->product_id);
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-stack', 'title' => __('No stock recorded'), 'message' => __('Post opening stock with an adjustment, or receive goods from a supplier.')];
    }
}
