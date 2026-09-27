<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $this->authorize('stock.view');

        $base = ProductStock::query()->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('products.deleted_at')->where('products.track_stock', true);
        $totals = (clone $base)->toBase()->selectRaw('COUNT(*) as skus, SUM(CASE WHEN product_stocks.quantity > 0 THEN product_stocks.quantity * products.cost_price ELSE 0 END) as cost, SUM(CASE WHEN product_stocks.quantity > 0 THEN product_stocks.quantity * products.retail_price ELSE 0 END) as retail')->first();

        return view('stock.index', [
            'skus' => (int) ($totals->skus ?? 0),
            'costValue' => $totals->cost ?? 0,
            'retailValue' => $totals->retail ?? 0,
            'low' => (clone $base)->where('product_stocks.quantity', '>', 0)->whereColumn('product_stocks.quantity', '<=', 'products.reorder_level')->count(),
            'out' => Product::query()->active()->sellable()->where('track_stock', true)
                ->whereDoesntHave('stocks', fn ($q) => $q->whereIn('branch_id', $context->activeIds())->where('quantity', '>', 0))->count(),
            'expiring' => ProductBatch::query()->where('quantity', '>', 0)->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today()->addDays((int) setting('inventory.expiry_alert_days', 30)))->count(),
        ]);
    }

    public function movements(Request $request): View
    {
        $this->authorize('stock.view');

        return view('stock.movements');
    }

    public function batches(Request $request): View
    {
        $this->authorize('stock.view');

        return view('stock.batches');
    }
}
