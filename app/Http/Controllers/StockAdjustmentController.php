<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\RejectAdjustmentRequest;
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
        $this->authorize('viewAny', StockAdjustment::class);

        return view('adjustments.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('adjustments.create');
    }

    public function show(Request $request, StockAdjustment $adjustment): View
    {
        $this->authorize('view', $adjustment);
        $adjustment->load(['items.product.unit', 'creator', 'approver', 'branch']);

        return view('adjustments.show', compact('adjustment'));
    }

    public function approve(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('approve', $adjustment);
        try {
            $this->service->approve($adjustment, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Adjustment approved and posted to stock.'));
    }

    public function reject(RejectAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('approve', $adjustment);
        $data = $request->validated();
        try {
            $this->service->reject($adjustment, $request->user(), $data['rejection_reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Adjustment rejected.'));
    }
}
