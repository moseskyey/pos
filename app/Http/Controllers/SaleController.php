<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['sales.view', 'sales.view_all']), 403);

        return view('sales.index', ['status' => $request->query('status') === 'layaway' ? 'layaway' : null]);
    }

    public function show(Request $request, Sale $sale): View
    {
        $user = $request->user();
        abort_unless($user->can('sales.view_all') || ($user->can('sales.view') && $sale->user_id === $user->id), 403);
        abort_if(in_array($sale->status, [SaleStatus::Held, SaleStatus::Quotation, SaleStatus::Converted], true) && ! $request->routeIs('quotations.*'), 404);

        $sale->load(['items.product', 'payments.receiver', 'customer', 'cashier', 'branch', 'register', 'voider', 'returns.items', 'returns.user']);
        $activities = Activity::query()->where('subject_type', $sale->getMorphClass())->where('subject_id', $sale->id)->with('causer')->latest()->get();
        $showProfit = $user->can('reports.profit.view');

        return view('sales.show', compact('sale', 'activities', 'showProfit'));
    }
}
