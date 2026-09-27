<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Http\Requests\CustomerRequest;
use App\Jobs\SendSms;
use App\Models\Customer;
use App\Models\SaleItem;
use App\Services\CustomerLedgerService;
use App\Services\CustomerStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(protected CustomerStatementService $statements) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('customers.index', [
            'count' => Customer::count(),
            'debtors' => Customer::where('balance', '>', 0)->count(),
            'owed' => Customer::sum('balance'),
            'storeCredit' => Customer::sum('store_credit'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.form', ['customer' => new Customer(['type' => 'retail', 'is_active' => true])]);
    }

    public function store(CustomerRequest $request, CustomerLedgerService $ledger): RedirectResponse
    {
        $data = $request->customerData();
        $customer = DB::transaction(function () use ($data, $ledger) {
            $customer = Customer::create(Arr::except($data, ['opening_balance']) + ['opening_balance' => $data['opening_balance'] ?? 0]);
            $ledger->openingBalance($customer, $data['opening_balance'] ?? 0);

            return $customer;
        });

        if ($request->boolean('save_new')) {
            return redirect()->route('customers.create')->with('success', __('Customer created.'));
        }

        return redirect()->route('customers.show', $customer)->with('success', __('Customer created.'));
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $stats = DB::table('sales')->where('customer_id', $customer->id)->where('status', SaleStatus::Completed->value)
            ->selectRaw('COUNT(*) as visits, COALESCE(SUM(total),0) as spent, MAX(created_at) as last_visit')->first();
        $favorites = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.customer_id', $customer->id)->where('sales.status', SaleStatus::Completed->value)
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->selectRaw('sale_items.product_id, sale_items.name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
            ->orderByDesc('qty')->limit(8)->get();

        return view('customers.show', [
            'customer' => $customer,
            'stats' => $stats,
            'favorites' => $favorites,
            'aging' => $this->statements->aging($customer),
            'ledger' => $customer->ledger()->with('user')->latest('id')->limit(100)->get(),
            'loyalty' => $customer->loyaltyTransactions()->with('sale')->limit(30)->get(),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.form', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->customerData());

        return redirect()->route('customers.show', $customer)->with('success', __('Customer updated.'));
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        if ($customer->balance > 0) {
            return back()->with('error', __('This customer still owes money.'));
        }
        $customer->update(['is_active' => false]);
        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer archived.'));
    }

    public function statement(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);
        $from = $request->date('from') ?? now()->subMonths(3)->startOfMonth();
        $to = $request->date('to') ?? now();
        $data = $this->statements->statement($customer, Carbon::instance($from), Carbon::instance($to));

        return Pdf::loadView('pdf.customer-statement', $data + ['title' => __('Statement'), 'docTitle' => __('Customer statement')])
            ->setPaper('a4')->stream('statement-'.Str::slug($customer->name).'.pdf');
    }

    public function remind(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('collect', Customer::class);
        if (! $customer->phone || $customer->balance <= 0) {
            return back()->with('error', __('This customer has no phone number or no balance.'));
        }
        SendSms::dispatch($customer->phone, $this->statements->reminderText($customer));
        activity('customers')->performedOn($customer)->log('Debt reminder SMS queued');

        return back()->with('success', __('Reminder sent.'));
    }
}
