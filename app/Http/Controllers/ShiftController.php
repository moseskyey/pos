<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Shift;
use App\Services\ShiftService;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function __construct(protected ShiftService $shifts) {}

    public function index(Request $request, BranchContext $context): View
    {
        abort_unless($request->user()->canAny(['shifts.open', 'shifts.manage']), 403);

        $trend = null;
        if ($request->user()->can('shifts.manage')) {
            $rows = Shift::query()->where('status', 'closed')->where('closed_at', '>=', now()->subDays(30))
                ->join('users', 'users.id', '=', 'shifts.user_id')
                ->groupBy('users.name')
                ->selectRaw('users.name as name, SUM(shifts.over_short) as total, COUNT(*) as shifts')
                ->orderBy('total')->get();
            $trend = ['type' => 'bar', 'horizontal' => true, 'labels' => $rows->pluck('name'), 'datasets' => [['label' => __('Over / short (30 days)'), 'data' => $rows->pluck('total')->map(fn ($v) => (float) $v), 'color' => '#DC2626']]];
        }

        return view('shifts.index', ['trend' => $trend]);
    }

    public function current(Request $request, BranchContext $context): View|RedirectResponse
    {
        abort_unless($request->user()->can('shifts.open'), 403);
        $shift = $this->shifts->current($request->user(), $context->currentId());
        if (! $shift) {
            return redirect()->route('pos')->with('info', __('You have no open shift. Open one to start selling.'));
        }

        return redirect()->route('shifts.show', $shift);
    }

    public function show(Request $request, Shift $shift): View
    {
        $this->authorizeShift($request, $shift);
        $shift->load(['user', 'register', 'closer', 'cashMovements.user']);
        $summary = $shift->isOpen() ? $this->shifts->summary($shift) : ($shift->summary ?? $this->shifts->summary($shift));
        $sales = $shift->sales()->with('customer')->whereIn('status', ['completed', 'voided', 'layaway'])->latest()->limit(50)->get();

        return view('shifts.show', compact('shift', 'summary', 'sales'));
    }

    public function cash(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($request->user()->can('cash.movements'), 403);
        $this->authorizeShift($request, $shift, own: true);
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        try {
            $this->shifts->cashMovement($shift, $request->user(), $data['type'], $data['amount'], $data['reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['type'] === 'in' ? __('Cash in recorded.') : __('Cash out recorded.'));
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizeShift($request, $shift, own: true);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'denominations' => ['array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $force = $shift->user_id !== $request->user()->id;
        try {
            $shift = $this->shifts->close($shift, $request->user(), $data['counted_cash'], $data['denominations'] ?? [], $data['note'] ?? null, $force);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        $status = Money::isZero($shift->over_short) ? __('Cash balanced.') : (Money::isNegative($shift->over_short) ? __('Short by :a.', ['a' => money(Money::abs($shift->over_short))]) : __('Over by :a.', ['a' => money($shift->over_short)]));

        return redirect()->route('shifts.show', $shift)->with('success', __('Shift closed.').' '.$status);
    }

    public function report(Request $request, Shift $shift, string $type): View
    {
        $this->authorizeShift($request, $shift);
        abort_unless(in_array($type, ['x', 'z'], true), 404);
        abort_if($type === 'z' && $shift->isOpen(), 404);
        $shift->load(['user', 'register', 'closer', 'branch']);
        $summary = $type === 'x' || ! $shift->summary ? $this->shifts->summary($shift) : $shift->summary;

        return view('shifts.report', ['shift' => $shift, 'summary' => $summary, 'type' => $type, 'paper' => setting('receipt.paper', '80mm')]);
    }

    protected function authorizeShift(Request $request, Shift $shift, bool $own = false): void
    {
        $user = $request->user();
        $allowed = $shift->user_id === $user->id || $user->can('shifts.manage');
        abort_unless($allowed, 403);
    }
}
