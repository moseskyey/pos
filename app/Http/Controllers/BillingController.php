<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\BillingPayRequest;
use App\Models\Platform\Plan;
use App\Models\Platform\SubscriptionPayment;
use App\Services\Platform\PlanLimits;
use App\Services\Platform\SubscriptionService;
use App\Support\PlatformSettings;
use App\Tenancy\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The business's own subscription: status, plan, paying by mobile money and
 * invoices. Reachable even after the subscription has ended.
 */
class BillingController extends Controller
{
    public function index(Request $request, SubscriptionService $subscriptions): View
    {
        $tenant = tenant()->load('plan');

        return view('billing.index', [
            'tenant' => $tenant,
            'status' => $tenant->status(),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(),
            'payments' => $tenant->payments()->with('plan')->latest('id')->limit(24)->get(),
            'usage' => PlanLimits::usage(),
            'canPay' => $request->user()->can('settings.manage'),
            'pushAvailable' => $subscriptions->pushAvailable(),
            'pending' => $tenant->payments()->where('method', 'fastlipa')->where('status', 'processing')->where('created_at', '>=', now()->subMinutes(10))->latest('id')->first(),
            'support' => [
                'phone' => PlatformSettings::get('support_phone'),
                'email' => PlatformSettings::get('support_email'),
                'whatsapp' => PlatformSettings::get('support_whatsapp'),
                'instructions' => PlatformSettings::get('billing_instructions'),
            ],
        ]);
    }

    public function pay(BillingPayRequest $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validated();
        try {
            $payment = $subscriptions->startPush(tenant(), Plan::findOrFail($data['plan_id']), (int) $data['periods'], $data['phone'], $request->user()->id);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        activity('billing')->causedBy($request->user())->withProperties(['reference' => $payment->reference, 'amount' => $payment->amount])->log('Subscription payment started');

        if ($payment->status === 'failed') {
            return back()->withInput()->with('error', $payment->message ?: __('The payment request was rejected. Check the number and try again.'));
        }

        return back()->with('success', __('Check your phone and enter your PIN to pay :amount.', ['amount' => money($payment->amount)]));
    }

    public function status(SubscriptionPayment $payment, SubscriptionService $subscriptions): JsonResponse
    {
        abort_unless($payment->tenant_id === tenant()->id, 404);
        if ($payment->status === 'processing' && $payment->updated_at->lt(now()->subSeconds(4))) {
            $payment = $subscriptions->refresh($payment);
        }

        return response()->json([
            'status' => $payment->status,
            'message' => $payment->message,
            'paid_until' => tenant()->fresh()->paid_until?->format('d/m/Y'),
        ]);
    }

    public function invoice(SubscriptionPayment $payment): Response
    {
        abort_unless($payment->tenant_id === tenant()->id && in_array($payment->status, ['completed', 'refunded'], true), 404);
        $payment->load('plan', 'tenant');

        return Pdf::loadView('pdf.subscription-invoice', ['payment' => $payment, 'title' => $payment->number])
            ->setPaper('a4')->stream($payment->number.'.pdf');
    }

    /** A platform admin who opened this business returns to the admin panel. */
    public function leaveAdminImpersonation(Request $request): RedirectResponse
    {
        $tenantId = tenant()?->id;
        abort_unless($request->session()->pull('admin_impersonator_id'), 403);

        activity('auth')->causedBy($request->user())->log('Platform admin left');
        Auth::guard('web')->logout();
        $request->session()->forget('tenant_id');
        app(TenantManager::class)->end();

        return redirect()->route('admin.tenants.show', $tenantId);
    }
}
