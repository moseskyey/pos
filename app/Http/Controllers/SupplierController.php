<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        return view('suppliers.index', ['owed' => Supplier::sum('balance'), 'count' => Supplier::count()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        return view('suppliers.form', ['supplier' => new Supplier(['payment_terms_days' => 30, 'is_active' => true])]);
    }

    public function store(SupplierRequest $request, SupplierLedgerService $ledger): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request, $ledger) {
            $supplier = Supplier::create(Arr::except($request->validated(), 'opening_balance') + ['opening_balance' => $request->validated('opening_balance') ?? 0]);
            if (($opening = $request->validated('opening_balance')) > 0) {
                $ledger->post($supplier, 'opening', 0, $opening, null, __('Opening balance'));
            }

            return $supplier;
        });

        return $request->boolean('save_new')
            ? redirect()->route('suppliers.create')->with('success', __('Supplier created.'))
            : redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier created.'));
    }

    public function show(Request $request, Supplier $supplier, SupplierLedgerService $ledger): View
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'aging' => $ledger->aging($supplier),
            'entries' => $supplier->ledger()->latest('id')->limit(100)->get(),
            'purchased' => DB::table('goods_receipts')->where('supplier_id', $supplier->id)->sum('total'),
        ]);
    }

    public function edit(Request $request, Supplier $supplier): View
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier updated.'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()->can('suppliers.manage'), 403);
        if ($supplier->balance > 0) {
            return back()->with('error', __('You still owe this supplier.'));
        }
        $supplier->update(['is_active' => false]);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier archived.'));
    }
}
