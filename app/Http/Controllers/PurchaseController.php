<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\EmailPurchaseOrderRequest;
use App\Http\Requests\ReorderOrderRequest;
use App\Http\Requests\SupplierBillRequest;
use App\Http\Requests\SupplierPaymentRequest;
use App\Mail\PurchaseOrderMail;
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
use Illuminate\Support\Facades\Mail;
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
        $this->authorize('viewAny', PurchaseOrder::class);

        return view('purchases.orders.index');
    }

    public function createOrder(Request $request): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchases.orders.form', ['order' => null, 'supplier' => $request->integer('supplier') ?: null]);
    }

    public function editOrder(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        return view('purchases.orders.form', ['order' => $purchaseOrder, 'supplier' => null]);
    }

    public function showOrder(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['items.product.unit', 'supplier', 'creator', 'receipts', 'branch']);

        return view('purchases.orders.show', ['order' => $purchaseOrder]);
    }

    public function sendOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('process', $purchaseOrder);
        try {
            $this->purchases->markSent($purchaseOrder, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Marked as sent. Download the PDF to share with the supplier.'));
    }

    /** Email the PO PDF to the supplier (queued) and mark a draft as sent. */
    public function emailOrder(EmailPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, ['draft', 'sent'], true)) {
            return back()->with('error', __('This purchase order cannot be sent.'));
        }
        $data = $request->validated();
        if ($purchaseOrder->status === 'draft') {
            $this->purchases->markSent($purchaseOrder, $request->user());
        }
        Mail::to($data['email'])->queue(new PurchaseOrderMail($purchaseOrder, $data['message'] ?? null));
        activity('purchases')->causedBy($request->user())->performedOn($purchaseOrder)->withProperties(['email' => $data['email']])->log('Purchase order emailed');

        return back()->with('success', __('Purchase order emailed to :e.', ['e' => $data['email']]));
    }

    public function cancelOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('process', $purchaseOrder);
        try {
            $this->purchases->cancelOrder($purchaseOrder, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Purchase order cancelled.'));
    }

    public function orderPdf(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['items.product.unit', 'supplier', 'branch', 'creator']);

        return Pdf::loadView('pdf.purchase-order', ['order' => $purchaseOrder, 'branch' => $purchaseOrder->branch, 'title' => $purchaseOrder->number, 'docTitle' => __('Purchase order')])
            ->setPaper('a4')->stream($purchaseOrder->number.'.pdf');
    }

    // Goods receipts ------------------------------------------------------------

    public function receipts(Request $request): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return view('purchases.receipts.index');
    }

    public function createReceipt(Request $request): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('purchases.receipts.create', ['order' => $request->integer('order') ?: null, 'supplier' => $request->integer('supplier') ?: null]);
    }

    public function showReceipt(Request $request, GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);
        $goodsReceipt->load(['items.product.unit', 'supplier', 'purchaseOrder', 'user', 'bill']);

        return view('purchases.receipts.show', ['receipt' => $goodsReceipt]);
    }

    // Bills & payments ------------------------------------------------------------

    public function bills(Request $request): View
    {
        $this->authorize('viewAny', SupplierBill::class);

        return view('purchases.bills.index', ['suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')]);
    }

    public function storeBill(SupplierBillRequest $request, BranchContext $context): RedirectResponse
    {
        $this->authorize('create', SupplierBill::class);
        $data = $request->validated();
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        $bill = $this->purchases->recordBill($branchId, Supplier::findOrFail($data['supplier_id']), $data, $request->user());

        return redirect()->route('supplier-bills.show', $bill)->with('success', __('Bill recorded.'));
    }

    public function showBill(Request $request, SupplierBill $supplierBill): View
    {
        $this->authorize('view', $supplierBill);
        $supplierBill->load(['supplier', 'goodsReceipt', 'allocations.payment']);

        return view('purchases.bills.show', ['bill' => $supplierBill]);
    }

    public function createPayment(Request $request): View
    {
        $this->authorize('pay', SupplierBill::class);
        $supplierId = $request->integer('supplier') ?: null;

        return view('purchases.payments.create', [
            'suppliers' => Supplier::where('balance', '>', 0)->orderBy('name')->get(),
            'selected' => $supplierId,
            'bills' => $supplierId ? SupplierBill::where('supplier_id', $supplierId)->where('status', '!=', 'paid')->orderBy('bill_date')->get() : collect(),
            'methods' => collect(PaymentMethod::cases())->reject(fn ($m) => in_array($m, [PaymentMethod::Credit, PaymentMethod::StoreCredit], true)),
        ]);
    }

    public function storePayment(SupplierPaymentRequest $request, SupplierPaymentService $service, BranchContext $context): RedirectResponse
    {
        $this->authorize('pay', SupplierBill::class);
        $data = $request->validated();
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
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
        $this->authorize('viewAny', PurchaseReturn::class);

        return view('purchases.returns.index');
    }

    public function createReturn(Request $request): View
    {
        $this->authorize('create', PurchaseReturn::class);

        return view('purchases.returns.create', ['receipt' => $request->integer('receipt') ?: null]);
    }

    public function showReturn(Request $request, PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('view', $purchaseReturn);
        $purchaseReturn->load(['items.product', 'supplier', 'goodsReceipt', 'user']);

        return view('purchases.returns.show', ['return' => $purchaseReturn]);
    }

    // Reorder -------------------------------------------------------------------------

    public function reorder(Request $request, ReorderService $reorder, BranchContext $context): View
    {
        $this->authorize('reorder', PurchaseOrder::class);
        $branchId = $context->currentId();

        return view('purchases.reorder', [
            'groups' => $branchId ? $reorder->suggestions($branchId) : collect(),
            'branch' => $context->current(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function createFromReorder(ReorderOrderRequest $request, BranchContext $context): RedirectResponse
    {
        $this->authorize('reorder', PurchaseOrder::class);
        $data = $request->validated();
        $items = array_values(array_filter($data['items'], fn ($i) => ! empty($i['selected']) && $i['quantity'] > 0));
        if (! $items) {
            return back()->with('error', __('Select at least one product.'));
        }
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        $order = $this->purchases->saveOrder($branchId, Supplier::findOrFail($data['supplier_id']), $items, $request->user(), ['note' => __('Created from reorder suggestions')]);

        return redirect()->route('purchase-orders.show', $order)->with('success', __('Draft purchase order :n created.', ['n' => $order->number]));
    }
}
