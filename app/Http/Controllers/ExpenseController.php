<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);
        $month = Expense::query()->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()]);
        $byCategory = (clone $month)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.name')->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')->orderByDesc('total')->pluck('total', 'name');

        return view('expenses.index', [
            'monthTotal' => (clone $month)->sum('amount'),
            'lastMonth' => Expense::query()->whereBetween('expense_date', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('amount'),
            'today' => Expense::query()->whereDate('expense_date', today())->sum('amount'),
            'chart' => ['type' => 'doughnut', 'labels' => $byCategory->keys(), 'datasets' => [['label' => __('This month'), 'data' => $byCategory->values()->map(fn ($v) => (float) $v)]]],
            'categories' => ExpenseCategory::withCount('expenses')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.form', $this->formData(new Expense(['expense_date' => today(), 'payment_method' => 'cash'])));
    }

    public function store(ExpenseRequest $request, ExpenseService $service, BranchContext $context): RedirectResponse
    {
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        try {
            $expense = $service->record($branchId, $request->validated(), $request->user(), $request->file('attachment'));
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $request->boolean('save_new')
            ? redirect()->route('expenses.create')->with('success', __('Expense :n recorded.', ['n' => $expense->number]))
            : redirect()->route('expenses.index')->with('success', __('Expense :n recorded.', ['n' => $expense->number]));
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.form', $this->formData($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = collect($request->validated())->except(['attachment', 'paid_from_drawer'])->all();
        if ($expense->paid_from_drawer) {
            unset($data['amount'], $data['payment_method']);
        }
        $expense->update($data);
        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path) {
                Storage::disk('local')->delete($expense->attachment_path);
            }
            $expense->update(['attachment_path' => $request->file('attachment')->store('expenses/'.now()->format('Y/m'), 'local')]);
        }

        return redirect()->route('expenses.index')->with('success', __('Expense updated.'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        if ($expense->paid_from_drawer && DB::table('shifts')->where('id', $expense->shift_id)->where('status', 'closed')->exists()) {
            return back()->with('error', __('This expense was paid from a closed shift and cannot be deleted.'));
        }
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', __('Expense deleted.'));
    }

    public function storeCategory(ExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);
        $data = $request->validated();
        ExpenseCategory::create($data + ['is_active' => true]);

        return back()->with('success', __('Category added.'));
    }

    public function recurring(Request $request): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.recurring');
    }

    protected function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => collect(PaymentMethod::cases())->reject(fn ($m) => in_array($m, [PaymentMethod::Credit, PaymentMethod::StoreCredit], true))->mapWithKeys(fn ($m) => [$m->value => $m->label()]),
        ];
    }
}
