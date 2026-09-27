<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\CustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerPayment::class);

        return view('customer-payments.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerPayment::class);

        return view('customer-payments.create', [
            'customers' => Customer::query()->where('balance', '>', 0)->orderBy('name')->get(),
            'selected' => $request->integer('customer') ?: null,
            'methods' => collect(PaymentMethod::enabled())->reject(fn ($m) => $m->isAccount()),
        ]);
    }

    public function store(CustomerPaymentRequest $request, CustomerPaymentService $service, BranchContext $context): RedirectResponse
    {
        $this->authorize('create', CustomerPayment::class);
        $data = $request->validated();
        $branchId = $context->currentId();
        if (! $branchId) {
            return back()->withInput()->with('error', __('Select a single branch in the navbar first.'));
        }
        try {
            $payment = $service->receive(Customer::findOrFail($data['customer_id']), $data['amount'], PaymentMethod::from($data['method']), $request->user(), $branchId, $data['reference'] ?? null, $data['note'] ?? null, $data['idempotency_key'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customers.show', $payment->customer_id)->with('success', __('Payment :n of :a received.', ['n' => $payment->number, 'a' => money($payment->amount)]));
    }

    public function show(Request $request, CustomerPayment $customerPayment): View
    {
        $this->authorize('view', $customerPayment);
        $customerPayment->load(['customer', 'user', 'allocations.sale']);

        return view('customer-payments.show', ['payment' => $customerPayment]);
    }
}
