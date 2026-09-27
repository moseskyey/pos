<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['stock.transfer', 'stock.transfer.approve']), 403);

        return view('transfers.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('stock.transfer'), 403);

        return view('transfers.create');
    }

    public function show(Request $request, StockTransfer $transfer, BranchContext $context): View
    {
        abort_unless($request->user()->canAny(['stock.transfer', 'stock.transfer.approve', 'stock.view']), 403);
        $transfer->load(['items.product.unit', 'fromBranch', 'toBranch', 'requester', 'approver', 'dispatcher', 'receiver']);

        return view('transfers.show', [
            'transfer' => $transfer,
            'atSource' => $context->canAccess($transfer->from_branch_id),
            'atDestination' => $context->canAccess($transfer->to_branch_id),
        ]);
    }

    public function approve(Request $request, StockTransfer $transfer): RedirectResponse
    {
        abort_unless($request->user()->can('stock.transfer.approve'), 403);

        return $this->run(fn () => $this->service->approve($transfer, $request->user()), __('Transfer approved.'));
    }

    public function dispatch(Request $request, StockTransfer $transfer, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('stock.transfer') && $context->canAccess($transfer->from_branch_id), 403);
        $data = $request->validate(['quantities' => ['array'], 'quantities.*' => ['numeric', 'min:0']]);

        return $this->run(fn () => $this->service->dispatch($transfer, $request->user(), $data['quantities'] ?? []), __('Transfer dispatched. Stock has left :branch.', ['branch' => $transfer->fromBranch->name]));
    }

    public function receive(Request $request, StockTransfer $transfer, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('stock.transfer') && $context->canAccess($transfer->to_branch_id), 403);
        $data = $request->validate(['quantities' => ['array'], 'quantities.*' => ['numeric', 'min:0'], 'notes' => ['array'], 'notes.*' => ['nullable', 'string', 'max:255']]);

        return $this->run(fn () => $this->service->receive($transfer, $request->user(), $data['quantities'] ?? [], $data['notes'] ?? []), __('Transfer received into stock.'));
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        abort_unless($request->user()->canAny(['stock.transfer', 'stock.transfer.approve']), 403);

        return $this->run(fn () => $this->service->cancel($transfer, $request->user()), __('Transfer cancelled.'));
    }

    protected function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException|InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
