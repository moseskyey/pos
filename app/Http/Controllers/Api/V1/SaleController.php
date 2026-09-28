<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiListRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public const STATUSES = ['completed', 'voided', 'layaway', 'quotation'];

    public function index(ApiListRequest $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->canAny(['sales.view', 'sales.view_all']), 403, __('Your account cannot view sales.'));
        $filters = $request->validated();
        [$from, $to] = $this->range($filters);

        return SaleResource::collection(Sale::query()->with(['customer', 'cashier'])
            ->whereIn('status', in_array($filters['status'] ?? null, self::STATUSES, true) ? [$filters['status']] : ['completed', 'voided', 'layaway'])
            ->whereBetween('created_at', [$from, $to])
            ->when(! $request->user()->can('sales.view_all'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('id')->paginate($request->perPage())->withQueryString());
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('document', $sale);
        abort_if($sale->status->value === 'held', 404);

        return new SaleResource($sale->load(['customer', 'cashier', 'items', 'payments']));
    }

    /** Totals for a period: sales, transactions, basket, VAT, discounts (and profit with permission). */
    public function summary(ApiListRequest $request, BranchContext $context): JsonResponse
    {
        abort_unless($request->user()->can('reports.view'), 403, __('Your account cannot view reports.'));
        [$from, $to] = $this->range($request->validated());
        $ids = $context->activeIds();
        $row = DB::table('sales')->where('status', 'completed')->whereIn('branch_id', $ids ?: [0])->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total),0) as total, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(discount_total),0) as discounts')->first();
        $data = [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'branch_ids' => $ids,
            'transactions' => (int) $row->n, 'total' => Money::round($row->total), 'tax' => Money::round($row->tax), 'discounts' => Money::round($row->discounts),
            'average_basket' => $row->n ? Money::div($row->total, $row->n) : '0.00',
        ];
        if ($request->user()->can('reports.profit.view')) {
            $cost = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')
                ->whereIn('sales.branch_id', $ids ?: [0])->whereBetween('sales.created_at', [$from, $to])->sum(DB::raw('sale_items.cost_price * sale_items.quantity'));
            $data['gross_profit'] = Money::sub(Money::sub($row->total, $row->tax), $cost);
        }

        return response()->json(['data' => $data]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(array $filters): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
