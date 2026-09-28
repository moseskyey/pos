<?php

namespace App\Services;

use App\Models\SalesTarget;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Commission and targets. A sale belongs to its salesperson (the cashier when
 * none was chosen). Commission is the person's rate × net sales without VAT,
 * less returns of their sales in the same period.
 */
class CommissionService
{
    /**
     * @param  array<int>  $branchIds
     * @return Collection<int, array{user: User, transactions: int, net_sales: string, returns: string, commissionable: string, rate: ?string, commission: string, target: ?string, achieved: ?float}>
     */
    public function summary(Carbon $from, Carbon $to, array $branchIds): Collection
    {
        $person = DB::raw('COALESCE(sales.salesperson_id, sales.user_id)');

        $sales = DB::table('sales')->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $branchIds ?: [0])->whereBetween('sales.created_at', [$from, $to])
            ->groupBy($person)
            ->selectRaw('COALESCE(sales.salesperson_id, sales.user_id) as person, COUNT(*) as transactions, SUM(sales.total - sales.tax_total) as net')
            ->get()->keyBy('person');

        $returns = DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->whereIn('sale_returns.branch_id', $branchIds ?: [0])->whereBetween('sale_returns.created_at', [$from, $to])
            ->groupBy($person)
            ->selectRaw('COALESCE(sales.salesperson_id, sales.user_id) as person, SUM(sale_returns.refund_total - sale_returns.tax_total) as net')
            ->get()->keyBy('person');

        $targets = SalesTarget::withoutGlobalScopes()->whereIn('branch_id', $branchIds ?: [0])->whereNotNull('user_id')
            ->whereIn('month', $this->months($from, $to))
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) as amount')->pluck('amount', 'user_id');

        $ids = $sales->keys()->merge($returns->keys())->merge($targets->keys())->unique()->filter();
        $users = User::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(function ($id) use ($sales, $returns, $targets, $users) {
            $user = $users->get($id);
            if (! $user) {
                return null;
            }
            $net = Money::round($sales->get($id)?->net ?? 0);
            $back = Money::round($returns->get($id)?->net ?? 0);
            $base = Money::max('0.00', Money::sub($net, $back));
            $rate = $user->commission_rate !== null ? (string) $user->commission_rate : null;
            $target = isset($targets[$id]) ? Money::round($targets[$id]) : null;

            return [
                'user' => $user,
                'transactions' => (int) ($sales->get($id)?->transactions ?? 0),
                'net_sales' => $net,
                'returns' => $back,
                'commissionable' => $base,
                'rate' => $rate,
                'commission' => $rate !== null ? Money::percent($base, $rate) : '0.00',
                'target' => $target,
                'achieved' => $target !== null && Money::isPositive($target) ? round((float) $base / (float) $target * 100, 1) : null,
            ];
        })->filter()->sortByDesc(fn ($row) => (float) $row['commissionable'])->values();
    }

    /** One person's progress this month (dashboard widget). */
    public function monthFor(User $user, array $branchIds): ?array
    {
        return $this->summary(now()->startOfMonth(), now()->endOfDay(), $branchIds)->firstWhere('user.id', $user->id);
    }

    /**
     * Save a month's targets: [user id or 'branch' => amount]; blank removes the target.
     *
     * @param  array<int|string, mixed>  $amounts
     */
    public function saveTargets(int $branchId, string $month, array $amounts, User $by): void
    {
        DB::transaction(function () use ($branchId, $month, $amounts, $by) {
            foreach ($amounts as $key => $amount) {
                $userId = $key === 'branch' ? null : (int) $key;
                $match = ['branch_id' => $branchId, 'user_id' => $userId, 'month' => $month];
                if ($amount === null || $amount === '' || ! Money::isPositive($amount)) {
                    SalesTarget::withoutGlobalScopes()->where($match)->delete();

                    continue;
                }
                SalesTarget::withoutGlobalScopes()->updateOrCreate($match, ['amount' => Money::round($amount)]);
            }
            activity('users')->causedBy($by)->withProperties(['branch_id' => $branchId, 'month' => $month])->log('Sales targets updated');
        });
    }

    /** @return array<int, string> YYYY-MM months touched by the range */
    protected function months(Carbon $from, Carbon $to): array
    {
        return collect(CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()))
            ->map(fn ($d) => $d->format('Y-m'))->all();
    }
}
