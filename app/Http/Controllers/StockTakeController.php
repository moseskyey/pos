<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
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
        abort_unless($request->user()->canAny(['stock.take', 'stock.take.approve']), 403);

        return view('stock-takes.index', ['categories' => Category::options()]);
    }

    public function store(Request $request, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('stock.take'), 403);
        $data = $request->validate(['category_id' => ['nullable', 'exists:categories,id'], 'note' => ['nullable', 'string', 'max:500']]);
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->with('error', __('Select a single branch in the navbar first.'));
        }
        $take = $this->service->create($branchId, $request->user(), $data['category_id'] ?? null, $data['note'] ?? null);

        return redirect()->route('stock-takes.show', $take)->with('success', __('Stock take started. Quantities are frozen as of now.'));
    }

    public function show(Request $request, StockTake $stockTake): View
    {
        abort_unless($request->user()->canAny(['stock.take', 'stock.take.approve']), 403);
        $stockTake->load(['branch', 'creator', 'approver', 'category']);

        return view('stock-takes.show', ['take' => $stockTake]);
    }

    public function submit(Request $request, StockTake $stockTake): RedirectResponse
    {
        abort_unless($request->user()->can('stock.take'), 403);

        return $this->run(fn () => $this->service->submit($stockTake, $request->user()), __('Stock take submitted for approval.'));
    }

    public function post(Request $request, StockTake $stockTake): RedirectResponse
    {
        abort_unless($request->user()->can('stock.take.approve'), 403);

        return $this->run(fn () => $this->service->post($stockTake, $request->user()), __('Variances posted to stock.'));
    }

    public function cancel(Request $request, StockTake $stockTake): RedirectResponse
    {
        abort_unless($request->user()->canAny(['stock.take', 'stock.take.approve']), 403);

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
