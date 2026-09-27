<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustmentService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['stock.adjust', 'stock.adjust.approve']), 403);

        return view('adjustments.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('stock.adjust'), 403);

        return view('adjustments.create');
    }

    public function show(Request $request, StockAdjustment $adjustment): View
    {
        abort_unless($request->user()->canAny(['stock.adjust', 'stock.adjust.approve', 'stock.view']), 403);
        $adjustment->load(['items.product.unit', 'creator', 'approver', 'branch']);

        return view('adjustments.show', compact('adjustment'));
    }

    public function approve(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        abort_unless($request->user()->can('stock.adjust.approve'), 403);
        try {
            $this->service->approve($adjustment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Adjustment approved and posted to stock.'));
    }

    public function reject(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        abort_unless($request->user()->can('stock.adjust.approve'), 403);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);
        try {
            $this->service->reject($adjustment, $request->user(), $data['rejection_reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Adjustment rejected.'));
    }
}
