<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Models\Platform\AdminActivity;
use App\Models\Platform\SubscriptionPayment;
use App\Services\Platform\SubscriptionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        return view('admin.payments.index', [
            'thisMonth' => Money::round(SubscriptionPayment::where('status', 'completed')->where('paid_at', '>=', now()->startOfMonth())->sum('amount')),
            'thisYear' => Money::round(SubscriptionPayment::where('status', 'completed')->where('paid_at', '>=', now()->startOfYear())->sum('amount')),
            'pending' => SubscriptionPayment::whereIn('status', ['pending', 'processing'])->count(),
            'failed' => SubscriptionPayment::where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    /** Ask FastLipa again (e.g. the business says it paid). */
    public function verify(SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        abort_unless($payment->method === 'fastlipa', 404);
        if ($payment->status === 'failed' && ! $payment->recheckable()) {
            // An admin may re-open an old failure for one more check.
            $payment->update(['recheck_until' => now()->addMinutes(5)]);
        }
        $payment = $subscriptions->refresh($payment->fresh());
        AdminActivity::record('payment.verified', "Checked payment {$payment->reference}: {$payment->status}", $payment->tenant);

        return back()->with($payment->status === 'completed' ? 'success' : 'info', __('Payment status: :status.', ['status' => __($payment->status)]));
    }

    public function refund(RefundPaymentRequest $request, SubscriptionPayment $payment, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            $payment = $subscriptions->refund($payment, $request->boolean('revoke'), $request->validated('note'));
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }
        AdminActivity::record('payment.refunded', "Refunded {$payment->number} (".money($payment->amount).')', $payment->tenant, ['revoke' => $request->boolean('revoke'), 'note' => $request->validated('note')]);

        return back()->with('success', __('Payment marked as refunded.'));
    }
}
