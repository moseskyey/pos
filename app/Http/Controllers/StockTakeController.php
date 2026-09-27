<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\StartStockTakeRequest;
use App\Models\Category;
use App\Models\StockTake;
use App\Services\StockTakeService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTakeController extends Controller
{
    public function __construct(protected StockTakeService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTake::class);

        return view('stock-takes.index', ['categories' => Category::options()]);
    }

    public function store(StartStockTakeRequest $request, BranchContext $context): RedirectResponse
    {
        $this->authorize('create', StockTake::class);
        $data = $request->validated();
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->with('error', __('Select a single branch in the navbar first.'));
        }
        $take = $this->service->create($branchId, $request->user(), $data['category_id'] ?? null, $data['note'] ?? null);

        return redirect()->route('stock-takes.show', $take)->with('success', __('Stock take started. Quantities are frozen as of now.'));
    }

    public function show(Request $request, StockTake $stockTake): View
    {
        $this->authorize('view', $stockTake);
        $stockTake->load(['branch', 'creator', 'approver', 'category']);

        return view('stock-takes.show', ['take' => $stockTake]);
    }

    public function submit(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('update', $stockTake);

        return $this->run(fn () => $this->service->submit($stockTake, $request->user()), __('Stock take submitted for approval.'));
    }

    public function post(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('approve', $stockTake);

        return $this->run(fn () => $this->service->post($stockTake, $request->user()), __('Variances posted to stock.'));
    }

    public function cancel(Request $request, StockTake $stockTake): RedirectResponse
    {
        $this->authorize('cancel', $stockTake);

        return $this->run(fn () => $this->service->cancel($stockTake, $request->user()), __('Stock take cancelled.'));
    }

    protected function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
