<?php

namespace App\Livewire\Dashboard;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\Shift;
use App\Reports\ReportFilters;
use App\Services\ShiftService;
use App\Support\BranchContext;
use App\Support\Money;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Overview extends Component
{
    #[Url(except: 'today')]
    public string $preset = 'today';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updatedPreset(): void
    {
        if ($this->preset !== 'custom') {
            $this->from = '';
            $this->to = '';
        }
    }

    protected function filters(): ReportFilters
    {
        [$from, $to] = ReportFilters::range($this->preset, $this->from ?: null, $this->to ?: null);

        return new ReportFilters($from, $to, app(BranchContext::class)->activeIds(), [], $this->preset);
    }

    protected function totals(ReportFilters $f): object
    {
        $sales = DB::table('sales')->where('status', 'completed')->whereIn('branch_id', $f->branchIds ?: [0])->whereBetween('created_at', [$f->from, $f->to])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(total - tax_total),0) as net')->first();
        $cost = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $f->branchIds ?: [0])->whereBetween('sales.created_at', [$f->from, $f->to])
            ->selectRaw('COALESCE(SUM(sale_items.cost_price * sale_items.quantity),0) as c')->value('c');

        return (object) ['count' => (int) $sales->count, 'total' => Money::round($sales->total), 'profit' => Money::sub($sales->net, $cost),
            'avg' => $sales->count ? Money::div($sales->total, $sales->count) : '0.00'];
    }

    protected function trend(string $now, string $before): ?float
    {
        return Money::isPositive($before) ? round(((float) $now - (float) $before) / (float) $before * 100, 1) : null;
    }

    public function render()
    {
        $user = auth()->user();
        $f = $this->filters();
        $ids = $f->branchIds ?: [0];
        $current = $this->totals($f);
        $previous = $this->totals($f->previous());
        $showProfit = $user->can('reports.profit.view');

        // Cash in open drawers.
        $shifts = Shift::query()->with(['user', 'register'])->where('status', 'open')->get();
        $shiftService = app(ShiftService::class);
        $shifts->each(fn ($s) => $s->setAttribute('expected', $shiftService->expectedCash($s)));

        // Charts ------------------------------------------------------------
        $days = DB::table('sales')->where('status', 'completed')->whereIn('branch_id', $ids)->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy(DB::raw(Sql::date('created_at')))->selectRaw(Sql::date('created_at').' as d, SUM(total) as t')->pluck('t', 'd');
        $labels = $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d M');
            $data[] = (float) ($days[$day->toDateString()] ?? 0);
        }
        $trendChart = ['type' => 'line', 'labels' => $labels, 'datasets' => [['label' => __('Sales'), 'data' => $data]]];

        $methods = DB::table('sale_payments')->join('sales', 'sales.id', '=', 'sale_payments.sale_id')->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $ids)->whereBetween('sales.created_at', [$f->from, $f->to])
            ->groupBy('sale_payments.method')->selectRaw('sale_payments.method as m, SUM(sale_payments.amount) as t')->orderByDesc('t')->pluck('t', 'm');
        $methodChart = ['type' => 'doughnut', 'labels' => $methods->keys()->map(fn ($m) => PaymentMethod::tryFrom($m)?->label() ?? $m)->values(), 'datasets' => [['label' => __('Payments'), 'data' => $methods->values()->map(fn ($v) => (float) $v)]]];

        $top = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $ids)->whereBetween('sales.created_at', [$f->from, $f->to])
            ->groupBy('sale_items.product_id', 'sale_items.name')->selectRaw('sale_items.name as n, SUM(sale_items.line_total) as t')->orderByDesc('t')->limit(10)->pluck('t', 'n');
        $topChart = ['type' => 'bar', 'horizontal' => true, 'labels' => $top->keys(), 'datasets' => [['label' => __('Revenue'), 'data' => $top->values()->map(fn ($v) => (float) $v)]]];

        $hourExpr = Sql::hour('created_at');
        $hours = DB::table('sales')->where('status', 'completed')->whereIn('branch_id', $ids)->whereBetween('created_at', [$f->from, $f->to])
            ->groupBy(DB::raw($hourExpr))->selectRaw("$hourExpr as h, SUM(total) as t")->pluck('t', 'h');
        $hourChart = ['type' => 'bar', 'labels' => array_map(fn ($h) => sprintf('%02d', $h), range(7, 22)), 'datasets' => [['label' => __('Sales'), 'data' => array_map(fn ($h) => (float) ($hours[$h] ?? 0), range(7, 22)), 'color' => '#0EA5E9']]];

        // Widgets ------------------------------------------------------------
        $lowStock = DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereIn('product_stocks.branch_id', $ids)->whereNull('products.deleted_at')->where('products.is_active', true)->where('products.track_stock', true)
            ->whereColumn('product_stocks.quantity', '<=', 'products.reorder_level')
            ->orderBy('product_stocks.quantity')->limit(8)->get(['products.id', 'products.name', 'product_stocks.quantity', 'products.reorder_level']);
        $expiring = ProductBatch::query()->with('product')->where('quantity', '>', 0)->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today()->addDays(30))->orderBy('expiry_date')->limit(6)->get();
        $topCustomers = DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id')->where('sales.status', 'completed')
            ->whereIn('sales.branch_id', $ids)->whereBetween('sales.created_at', [$f->from->copy()->min(now()->subDays(29)->startOfDay()), $f->to])
            ->groupBy('customers.id', 'customers.name')->selectRaw('customers.id, customers.name, COUNT(*) as visits, SUM(sales.total) as spent')->orderByDesc('spent')->limit(5)->get();
        $recent = Sale::query()->with('customer')->where('status', 'completed')->latest()->limit(8)->get();

        return view('livewire.dashboard.overview', [
            'f' => $f,
            'current' => $current,
            'previous' => $previous,
            'trend' => fn ($a, $b) => $this->trend($a, $b),
            'showProfit' => $showProfit,
            'cashInDrawer' => Money::sum($shifts, 'expected'),
            'debts' => Money::round(Customer::sum('balance')),
            'debtors' => Customer::where('balance', '>', 0)->count(),
            'shifts' => $shifts,
            'trendChart' => $trendChart,
            'methodChart' => $methodChart,
            'topChart' => $topChart,
            'hourChart' => $hourChart,
            'lowStock' => $lowStock,
            'expiring' => $expiring,
            'topCustomers' => $topCustomers,
            'recent' => $recent,
        ]);
    }
}
