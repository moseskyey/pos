<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        return view('suppliers.index', ['owed' => Supplier::sum('balance'), 'count' => Supplier::count()]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Supplier::class);

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
        $this->authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'aging' => $ledger->aging($supplier),
            'entries' => $supplier->ledger()->latest('id')->limit(100)->get(),
            'purchased' => DB::table('goods_receipts')->where('supplier_id', $supplier->id)->sum('total'),
        ]);
    }

    public function edit(Request $request, Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier updated.'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);
        if ($supplier->balance > 0) {
            return back()->with('error', __('You still owe this supplier.'));
        }
        $supplier->update(['is_active' => false]);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier archived.'));
    }

    public function statement(Request $request, Supplier $supplier, SupplierLedgerService $ledger): Response
    {
        $this->authorize('view', $supplier);
        $from = $request->date('from') ?? now()->subMonths(3)->startOfMonth();
        $to = $request->date('to') ?? now();
        $data = $ledger->statement($supplier, Carbon::instance($from), Carbon::instance($to));

        return Pdf::loadView('pdf.supplier-statement', $data + ['title' => __('Statement'), 'docTitle' => __('Supplier statement')])
            ->setPaper('a4')->stream('supplier-statement-'.Str::slug($supplier->name).'.pdf');
    }
}
