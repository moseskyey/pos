<?php

namespace App\Livewire\Tables;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class StockMovementsTable extends DataTable
{
    #[Locked]
    public ?int $productId = null;

    protected function title(): string
    {
        return __('Stock movements');
    }

    protected function query(): Builder
    {
        return StockMovement::query()->with(['product.unit', 'user', 'branch', 'batch', 'reference'])
            ->when($this->productId, fn ($q) => $q->where('product_id', $this->productId));
    }

    protected function searchable(): array
    {
        return $this->productId ? ['note'] : ['product.name', 'product.sku', 'note'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('type', __('Types'), MovementType::options()),
            Filter::select('user_id', __('Users'), User::query()->orderBy('name')->pluck('name', 'id')->all()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Product'))->visible(! $this->productId)->html(fn ($m) => '<a href="'.route('products.show', $m->product_id).'" class="fw-semibold text-decoration-none">'.e($m->product?->name).'</a>')->exportAs(fn ($m) => $m->product?->name),
            Column::make(__('Branch'))->format(fn ($m) => $m->branch?->code),
            Column::make(__('Type'), 'type')->html(fn ($m) => '<span class="badge rounded-pill text-bg-'.$m->type->color().'-soft">'.e($m->type->label()).'</span>')->exportAs(fn ($m) => $m->type->label()),
            Column::make(__('Qty'), 'quantity')->number()->html(fn ($m) => '<span class="fw-semibold '.($m->quantity < 0 ? 'text-danger' : 'text-success').'">'.($m->quantity > 0 ? '+' : '').e(qty($m->quantity)).'</span>'),
            Column::make(__('Balance'), 'balance_after')->number(),
            Column::make(__('Batch'))->format(fn ($m) => $m->batch?->batch_no ?? '—'),
            Column::make(__('Reference'))->html(fn ($m) => $this->referenceLink($m))->exportAs(fn ($m) => $m->referenceNumber()),
            Column::make(__('User'))->format(fn ($m) => $m->user?->name ?? '—'),
            Column::make(__('Note'), 'note')->format(fn ($m) => Str::limit($m->note, 40) ?: '—'),
        ];
    }

    protected function referenceLink(StockMovement $m): string
    {
        $number = $m->referenceNumber();
        if (! $number) {
            return '—';
        }
        $route = match (class_basename((string) $m->reference_type)) {
            'Sale' => 'sales.show', 'SaleReturn' => 'returns.show', 'StockAdjustment' => 'adjustments.show', 'StockTransfer' => 'transfers.show',
            'StockTake' => 'stock-takes.show', 'GoodsReceipt' => 'goods-receipts.show', 'PurchaseReturn' => 'purchase-returns.show', default => null,
        };

        return $route && Route::has($route)
            ? '<a href="'.route($route, $m->reference_id).'" class="font-monospace small text-decoration-none">'.e($number).'</a>'
            : '<span class="font-monospace small">'.e($number).'</span>';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-arrow-left-right', 'title' => __('No stock movements yet')];
    }
}
