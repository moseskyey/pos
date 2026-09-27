<?php

namespace App\Services\Platform;

use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\Tenant;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Figures for the platform admin dashboard. */
class PlatformMetrics
{
    public function counts(): array
    {
        $counts = ['total' => Tenant::count()];
        foreach (array_keys(Tenant::statusLabels()) as $status) {
            $counts[$status] = Tenant::whereStatus($status)->count();
        }
        $counts['deleted'] = Tenant::onlyTrashed()->count();
        $counts['new_this_month'] = Tenant::where('created_at', '>=', now()->startOfMonth())->count();

        return $counts;
    }

    /** Monthly recurring revenue: paying businesses at their plan's monthly price. */
    public function mrr(): string
    {
        return Money::sum(
            Tenant::whereStatus(Tenant::ACTIVE)->with('plan')->get()->filter->plan,
            fn (Tenant $t) => $t->plan->monthlyPrice(),
        );
    }

    public function revenueBetween(Carbon $from, Carbon $to): string
    {
        return Money::round(SubscriptionPayment::where('status', 'completed')->whereBetween('paid_at', [$from, $to])->sum('amount'));
    }

    /** @return array{labels: array<int, string>, revenue: array<int, float>, signups: array<int, int>} */
    public function lastMonths(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $payments = SubscriptionPayment::where('status', 'completed')->where('paid_at', '>=', $start)->get(['amount', 'paid_at']);
        $signups = Tenant::withTrashed()->where('created_at', '>=', $start)->get(['created_at']);

        $labels = $revenue = $joins = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $month->translatedFormat('M Y');
            $revenue[] = (float) Money::sum($payments->filter(fn ($p) => $p->paid_at->format('Y-m') === $key), 'amount');
            $joins[] = $signups->filter(fn ($t) => $t->created_at->format('Y-m') === $key)->count();
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'signups' => $joins];
    }

    public function expiringSoon(int $days = 7): Collection
    {
        $until = now()->addDays($days)->endOfDay();

        return Tenant::with('plan')->whereNull('suspended_at')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereBetween('paid_until', [now(), $until]))
                ->orWhere(fn ($w) => $w->whereBetween('trial_ends_at', [now(), $until])->where(fn ($p) => $p->whereNull('paid_until')->orWhere('paid_until', '<', now()))))
            ->get()->sortBy(fn (Tenant $t) => $t->accessEndsAt())->values();
    }
}
