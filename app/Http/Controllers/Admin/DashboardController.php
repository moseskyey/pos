<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\Tenant;
use App\Services\Platform\PlatformMetrics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): View
    {
        $thisMonth = $metrics->revenueBetween(now()->startOfMonth(), now());
        $lastMonth = $metrics->revenueBetween(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());

        return view('admin.dashboard', [
            'counts' => $metrics->counts(),
            'mrr' => $metrics->mrr(),
            'thisMonth' => $thisMonth,
            'trend' => (float) $lastMonth > 0 ? round(((float) $thisMonth - (float) $lastMonth) / (float) $lastMonth * 100, 1) : null,
            'series' => $metrics->lastMonths(),
            'expiring' => $metrics->expiringSoon(),
            'recentPayments' => SubscriptionPayment::with('tenant', 'plan')->where('status', 'completed')->latest('paid_at')->limit(8)->get(),
            'recentSignups' => Tenant::with('plan')->latest()->limit(8)->get(),
            'pendingCount' => SubscriptionPayment::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}
