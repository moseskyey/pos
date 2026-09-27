<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('customers.payments'), 403);

        return view('customer-payments.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('customers.payments'), 403);

        return view('customer-payments.create', [
            'customers' => Customer::query()->where('balance', '>', 0)->orderBy('name')->get(),
            'selected' => $request->integer('customer') ?: null,
            'methods' => collect(PaymentMethod::enabled())->reject(fn ($m) => in_array($m, [PaymentMethod::Credit, PaymentMethod::StoreCredit], true)),
        ]);
    }

    public function store(Request $request, CustomerPaymentService $service, BranchContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('customers.payments'), 403);
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $branchId = $context->currentId() ?? $request->user()->default_branch_id ?? $context->accessibleIds()[0];
        try {
            $payment = $service->receive(Customer::findOrFail($data['customer_id']), $data['amount'], PaymentMethod::from($data['method']), $request->user(), $branchId, $data['reference'] ?? null, $data['note'] ?? null, $data['idempotency_key'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customers.show', $payment->customer_id)->with('success', __('Payment :n of :a received.', ['n' => $payment->number, 'a' => money($payment->amount)]));
    }

    public function show(Request $request, CustomerPayment $customerPayment): View
    {
        abort_unless($request->user()->can('customers.payments') || $request->user()->can('customers.view'), 403);
        $customerPayment->load(['customer', 'user', 'allocations.sale']);

        return view('customer-payments.show', ['payment' => $customerPayment]);
    }
}
