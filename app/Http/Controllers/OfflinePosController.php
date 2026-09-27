<?php

namespace App\Http\Controllers;

use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\OfflineSyncRequest;
use App\Models\Product;
use App\Models\Shift;
use App\Services\SaleService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * Offline till: a lightweight POS page the service worker keeps available
 * without a connection, its product catalogue, and the sync endpoint that
 * records queued sales once the connection is back.
 */
class OfflinePosController extends Controller
{
    public function page(Request $request): View
    {
        $this->authorize('pos.access');

        return view('pos.offline');
    }

    public function catalog(Request $request, ShiftService $shifts, StockService $stock): JsonResponse
    {
        $this->authorize('pos.access');
        $branch = current_branch();
        abort_unless($branch, 422, __('Select a single branch in the navbar first.'));

        $products = Product::query()->with(['barcodes:id,product_id,barcode', 'unit:id,short_name'])
            ->where('is_active', true)->where('has_variants', false)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_id', 'retail_price', 'wholesale_price', 'wholesale_min_qty', 'tax_type', 'track_stock', 'is_weighted']);
        $available = $stock->availableMany($branch->id, $products->pluck('id')->all());
        $shift = $shifts->current($request->user(), $branch->id);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'user' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'shift_id' => $shift?->id,
            'settings' => [
                'business' => setting('business.name'),
                'symbol' => setting('currency.symbol', 'TSh'),
                'prices_include_vat' => (bool) setting('tax.prices_include_vat', true),
                'rounding' => (int) setting('pos.rounding', 0),
                'usd_rate' => (string) setting('currency.usd_rate'),
                'footer' => setting('receipt.footer'),
            ],
            'methods' => OfflineSyncRequest::methodOptions(),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcodes' => $p->barcodes->pluck('barcode')->all(),
                'unit' => $p->unit?->short_name,
                'price' => (string) $p->retail_price,
                'wholesale_price' => $p->wholesale_price !== null ? (string) $p->wholesale_price : null,
                'wholesale_min_qty' => $p->wholesale_min_qty !== null ? (string) $p->wholesale_min_qty : null,
                'tax_rate' => (string) $p->taxRate(),
                'track_stock' => $p->track_stock,
                'decimal' => (bool) $p->is_weighted,
                'stock' => $available[$p->id] ?? '0.000',
            ])->values(),
        ]);
    }

    /** Session check + fresh CSRF token before syncing (the cached page's token may be stale). */
    public function ping(Request $request): JsonResponse
    {
        // The browser checks this matches the key its offline queue is stored under.
        return response()->json(['user_id' => device_key(), 'csrf' => csrf_token()]);
    }

    public function sync(OfflineSyncRequest $request, SaleService $sales, ShiftService $shifts): JsonResponse
    {
        $user = $request->user();
        $results = [];
        foreach ($request->validated('sales') as $data) {
            $clientId = $data['client_id'];
            try {
                $shift = ! empty($data['shift_id'])
                    ? Shift::withoutGlobalScopes()->where('id', $data['shift_id'])->where('user_id', $user->id)->first()
                    : null;
                $shift ??= $shifts->current($user);
                if (! $shift) {
                    throw new BusinessRuleException(__('Open a shift, then sync again.'));
                }
                $sale = $sales->recordOffline(
                    ['lines' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price']], $data['lines']),
                        'note' => $data['note'] ?? null],
                    $data['payments'], $user, $shift, $clientId, Carbon::parse($data['sold_at']),
                );
                $results[] = ['client_id' => $clientId, 'status' => 'synced', 'number' => $sale->number, 'id' => $sale->id, 'flags' => $sale->review_flags ?? []];
            } catch (BusinessRuleException|ApprovalRequiredException $e) {
                $results[] = ['client_id' => $clientId, 'status' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['client_id' => $clientId, 'status' => 'retry', 'message' => __('Could not save this sale yet. It will be retried.')];
            }
        }

        return response()->json(['results' => $results]);
    }
}
