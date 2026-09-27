@php
    $revenueChart = ['type' => 'bar', 'labels' => $series['labels'], 'datasets' => [['label' => __('Revenue'), 'data' => $series['revenue']]]];
    $signupChart = ['type' => 'line', 'money' => false, 'labels' => $series['labels'], 'datasets' => [['label' => __('Sign-ups'), 'data' => $series['signups'], 'color' => '#16A34A']]];
@endphp
<x-layouts.admin :title="__('Dashboard')">
    <x-page-header :title="__('Platform overview')" :subtitle="__(':count businesses · :new new this month', ['count' => number_format($counts['total']), 'new' => number_format($counts['new_this_month'])])">
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-cash-coin"></i> {{ __('Payments') }}
            @if ($pendingCount)<span class="badge text-bg-warning ms-1">{{ $pendingCount }}</span>@endif</a>
        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add business') }}</a>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Monthly recurring revenue')" :value="money($mrr)" icon="bi-graph-up-arrow" color="success" :hint="__('From paying businesses')" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Revenue this month')" :value="money($thisMonth)" icon="bi-cash-stack" color="primary" :trend="$trend" :hint="__('vs last month')" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Paying businesses')" :value="number_format($counts['active'])" icon="bi-patch-check" color="info" :href="route('admin.tenants.index', ['filters' => ['status' => 'active']])" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('On free trial')" :value="number_format($counts['trial'])" icon="bi-hourglass-split" color="warning" :href="route('admin.tenants.index', ['filters' => ['status' => 'trial']])" /></div>
    </div>

    <div class="row g-3 mb-3">
        @foreach (['grace' => ['bi-exclamation-triangle', 'warning'], 'expired' => ['bi-x-octagon', 'danger'], 'suspended' => ['bi-slash-circle', 'secondary']] as $status => [$icon, $color])
            <div class="col-md-4">
                <a href="{{ route('admin.tenants.index', ['filters' => ['status' => $status]]) }}" class="card text-decoration-none text-reset hover-lift h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <span class="stat-icon bg-{{ $color }}-soft text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                        <span class="flex-grow-1">{{ \App\Models\Platform\Tenant::statusLabels()[$status] }}</span>
                        <span class="fs-4 fw-bold">{{ number_format($counts[$status]) }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <x-card :title="__('Subscription revenue')" :subtitle="__('Last 12 months')" icon="bi-bar-chart">
                <div style="height: 280px"><canvas data-chart='@json($revenueChart)' aria-label="{{ __('Revenue chart') }}" role="img"></canvas></div>
            </x-card>
        </div>
        <div class="col-lg-5">
            <x-card :title="__('New businesses')" :subtitle="__('Last 12 months')" icon="bi-graph-up">
                <div style="height: 280px"><canvas data-chart='@json($signupChart)' aria-label="{{ __('Sign-ups chart') }}" role="img"></canvas></div>
            </x-card>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-card :title="__('Ending in the next 7 days')" icon="bi-calendar-x" :flush="true">
                @if ($expiring->isEmpty())
                    <x-empty-state icon="bi-calendar-check" :title="__('Nothing ending soon')" :message="__('No trials or subscriptions end this week.')" />
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($expiring as $t)
                            <a href="{{ route('admin.tenants.show', $t) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-truncate">{{ $t->name }}</div><div class="small text-body-secondary text-truncate">{{ $t->owner_name }} · {{ $t->owner_phone ? \App\Support\PhoneNumber::display($t->owner_phone) : $t->owner_email }}</div></div>
                                <x-status-badge :status="$t->status()" :label="\App\Models\Platform\Tenant::statusLabels()[$t->status()]" />
                                <span class="small text-nowrap">{{ $t->accessEndsAt()?->format('d/m') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('Recent payments')" icon="bi-receipt" :flush="true">
                <x-slot:actions><a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-light">{{ __('View all') }}</a></x-slot:actions>
                @if ($recentPayments->isEmpty())
                    <x-empty-state icon="bi-cash" :title="__('No payments yet')" />
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($recentPayments as $p)
                            <a href="{{ route('admin.tenants.show', $p->tenant_id) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-truncate">{{ $p->tenant?->name }}</div><div class="small text-body-secondary">{{ $p->plan?->name }} · {{ \App\Models\Platform\SubscriptionPayment::methodLabels()[$p->method] ?? $p->method }}</div></div>
                                <div class="text-end"><div class="fw-semibold"><x-money :amount="$p->amount" /></div><div class="small text-body-secondary">{{ $p->paid_at?->diffForHumans() }}</div></div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-12">
            <x-card :title="__('Newest businesses')" icon="bi-shop" :flush="true">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Signed up') }}</th></tr></thead>
                        <tbody>
                        @forelse ($recentSignups as $t)
                            <tr class="cursor-pointer" onclick="location.href='{{ route('admin.tenants.show', $t) }}'">
                                <td class="fw-semibold"><a href="{{ route('admin.tenants.show', $t) }}" class="text-reset text-decoration-none">{{ $t->name }}</a></td>
                                <td class="small">{{ $t->owner_name }}<div class="text-body-secondary">{{ $t->owner_email }}</div></td>
                                <td>{{ $t->plan?->name ?? '—' }}</td>
                                <td><x-status-badge :status="$t->status()" :label="\App\Models\Platform\Tenant::statusLabels()[$t->status()]" /></td>
                                <td class="small text-nowrap">{{ $t->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="bi-shop" :title="__('No businesses yet')" :action="route('admin.tenants.create')" :actionLabel="__('Add business')" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
