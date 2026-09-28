<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesTargetsRequest;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\CommissionService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TargetController extends Controller
{
    public function __construct(protected CommissionService $commission) {}

    public function index(Request $request, BranchContext $context): View
    {
        abort_unless($request->user()->can('targets.manage'), 403);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $branch = $context->current();
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        return view('targets.index', [
            'month' => $month,
            'branch' => $branch,
            'people' => $branch ? $this->salespeople($branch->id) : collect(),
            'targets' => $branch ? SalesTarget::withoutGlobalScopes()->where('branch_id', $branch->id)->where('month', $month)->get()->keyBy(fn ($t) => $t->user_id ?? 'branch') : collect(),
            'progress' => $branch ? $this->commission->summary($start, $start->copy()->endOfMonth()->min(now()->endOfDay()), [$branch->id])->keyBy('user.id') : collect(),
        ]);
    }

    public function update(SalesTargetsRequest $request, BranchContext $context): RedirectResponse
    {
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->with('error', __('Select a single branch in the navbar first.'));
        }
        $data = $request->validated();
        $allowed = $this->salespeople($branchId)->pluck('id')->all();
        $targets = collect($data['targets'] ?? [])->filter(fn ($v, $k) => $k === 'branch' || in_array((int) $k, $allowed, true))->all();
        $this->commission->saveTargets($branchId, $data['month'], $targets, $request->user());

        foreach ($data['rates'] ?? [] as $userId => $rate) {
            if (in_array((int) $userId, $allowed, true)) {
                User::whereKey($userId)->update(['commission_rate' => $rate === null || $rate === '' ? null : $rate]);
            }
        }

        return redirect()->route('targets.index', ['month' => $data['month']])->with('success', __('Targets saved.'));
    }

    /** Active people who can sell at this branch. */
    protected function salespeople(int $branchId)
    {
        return User::query()->where('is_active', true)->whereHas('branches', fn ($q) => $q->whereKey($branchId))
            ->orderBy('name')->get()->filter(fn (User $u) => $u->can('pos.access') || $u->can('sales.create'))->values();
    }
}
