<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\PurchaseService;
use App\Services\ReorderService;
use App\Services\SupplierPaymentService;
use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Purchase orders, GRNs, supplier bills & payments, purchase returns, reorder.
 */
class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    // Purchase orders ---------------------------------------------------------

    public function orders(Request $request): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return view('purchases.orders.index');
    }

    public function createOrder(Request $request): View
    {
        abort_unless($request->user()->can('purchases.manage'), 403);

        return view('purchases.orders.form', ['order' => null, 'supplier' => $request->integer('supplier') ?: null]);
    }

    public function editOrder(Request $request, PurchaseOrder $purchaseOrder): View
    {
        abort_unless($request->user()->can('purchases.manage') && $purchaseOrder->status === 'draft', 403);

        return view('purchases.orders.form', ['order' => $purchaseOrder, 'supplier' => null]);
    }

    public function showOrder(Request $request, PurchaseOrder $purchaseOrder): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);
        $purchaseOrder->load(['items.product.unit', 'supplier', 'creator', 'receipts', 'branch']);

        return view('purchases.orders.show', ['order' => $purchaseOrder]);
    }

    public function sendOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->can('purchases.manage'), 403);
        try {
            $this->purchases->markSent($purchaseOrder, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Marked as sent. Download the PDF to share with the supplier.'));
    }

    public function cancelOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->can('purchases.manage'), 403);
        try {
            $this->purchases->cancelOrder($purchaseOrder, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Purchase order cancelled.'));
    }

    public function orderPdf(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()->can('purchases.view'), 403);
        $purchaseOrder->load(['items.product.unit', 'supplier', 'branch', 'creator']);

        return Pdf::loadView('pdf.purchase-order', ['order' => $purchaseOrder, 'branch' => $purchaseOrder->branch, 'title' => $purchaseOrder->number, 'docTitle' => __('Purchase order')])
            ->setPaper('a4')->stream($purchaseOrder->number.'.pdf');
    }

    // Goods receipts ------------------------------------------------------------

    public function receipts(Request $request): View
    {
        abort_unless($request->user()->canAny(['purchases.view', 'purchases.receive']), 403);

        return view('purchases.receipts.index');
    }

    public function createReceipt(Request $request): View
    {
        abort_unless($request->user()->can('purchases.receive'), 403);

        return view('purchases.receipts.create', ['order' => $request->integer('order') ?: null, 'supplier' => $request->integer('supplier') ?: null]);
    }

    public function showReceipt(Request $request, GoodsReceipt $goodsReceipt): View
    {
        abort_unless($request->user()->canAny(['purchases.view', 'purchases.receive']), 403);
        $goodsReceipt->load(['items.product.unit', 'supplier', 'purchaseOrder', 'user', 'bill']);

        return view('purchases.receipts.show', ['receipt' => $goodsReceipt]);
    }

    // Bills & payments ------------------------------------------------------------

    public function bills(Request $request): View
    {
        abort_unless($request->user()->can('supplier.payments'), 403);

        return view('purchases.bills.index', ['suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')]);
    }

    public function storeBill(Request $request, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('supplier.payments'), 403);
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'bill_no' => ['nullable', 'string', 'max:64'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'total' => ['required', 'numeric', 'gt:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0', 'lte:total'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $branchId = $context->currentId() ?? $context->accessibleIds()[0];
        $bill = $this->purchases->recordBill($branchId, Supplier::findOrFail($data['supplier_id']), $data, $request->user());

        return redirect()->route('supplier-bills.show', $bill)->with('success', __('Bill recorded.'));
    }

    public function showBill(Request $request, SupplierBill $supplierBill): View
    {
        abort_unless($request->user()->can('supplier.payments'), 403);
        $supplierBill->load(['supplier', 'goodsReceipt', 'allocations.payment']);

        return view('purchases.bills.show', ['bill' => $supplierBill]);
    }

    public function createPayment(Request $request): View
    {
        abort_unless($request->user()->can('supplier.payments'), 403);
        $supplierId = $request->integer('supplier') ?: null;

        return view('purchases.payments.create', [
            'suppliers' => Supplier::where('balance', '>', 0)->orderBy('name')->get(),
            'selected' => $supplierId,
            'bills' => $supplierId ? SupplierBill::where('supplier_id', $supplierId)->where('status', '!=', 'paid')->orderBy('bill_date')->get() : collect(),
            'methods' => collect(PaymentMethod::cases())->reject(fn ($m) => in_array($m, [PaymentMethod::Credit, PaymentMethod::StoreCredit], true)),
        ]);
    }

    public function storePayment(Request $request, SupplierPaymentService $service, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('supplier.payments'), 403);
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'from_drawer' => ['boolean'],
            'allocations' => ['array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $branchId = $context->currentId() ?? $context->accessibleIds()[0];
        $allocations = array_filter($data['allocations'] ?? [], fn ($v) => (float) $v > 0);
        if ($request->boolean('from_drawer')) {
            $allocations['from_drawer'] = true;
        }
        try {
            $service->pay(Supplier::findOrFail($data['supplier_id']), $data['amount'], PaymentMethod::from($data['method']), $request->user(), $branchId, $allocations, $data['reference'] ?? null, $data['note'] ?? null, $data['paid_at']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('suppliers.show', $data['supplier_id'])->with('success', __('Payment recorded.'));
    }

    // Returns ------------------------------------------------------------------------

    public function returns(Request $request): View
    {
        abort_unless($request->user()->can('purchases.return'), 403);

        return view('purchases.returns.index');
    }

    public function createReturn(Request $request): View
    {
        abort_unless($request->user()->can('purchases.return'), 403);

        return view('purchases.returns.create', ['receipt' => $request->integer('receipt') ?: null]);
    }

    public function showReturn(Request $request, PurchaseReturn $purchaseReturn): View
    {
        abort_unless($request->user()->can('purchases.return'), 403);
        $purchaseReturn->load(['items.product', 'supplier', 'goodsReceipt', 'user']);

        return view('purchases.returns.show', ['return' => $purchaseReturn]);
    }

    // Reorder -------------------------------------------------------------------------

    public function reorder(Request $request, ReorderService $reorder, BranchContext $context): View
    {
        abort_unless($request->user()->can('purchases.manage'), 403);
        $branchId = $context->currentId();

        return view('purchases.reorder', [
            'groups' => $branchId ? $reorder->suggestions($branchId) : collect(),
            'branch' => $context->current(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function createFromReorder(Request $request, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('purchases.manage'), 403);
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.selected' => ['nullable', 'boolean'],
        ]);
        $items = array_values(array_filter($data['items'], fn ($i) => ! empty($i['selected']) && $i['quantity'] > 0));
        if (! $items) {
            return back()->with('error', __('Select at least one product.'));
        }
        $order = $this->purchases->saveOrder($context->currentId(), Supplier::findOrFail($data['supplier_id']), $items, $request->user(), ['note' => __('Created from reorder suggestions')]);

        return redirect()->route('purchase-orders.show', $order)->with('success', __('Draft purchase order :n created.', ['n' => $order->number]));
    }
}
