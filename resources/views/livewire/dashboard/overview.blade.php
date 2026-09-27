<div>
    <div class="page-header">
        <div>
            <h2>{{ __('Karibu, :name!', ['name' => explode(' ', auth()->user()->name)[0]]) }} 👋</h2>
            <p class="page-subtitle">{{ current_branch()?->name ?? __('All branches') }} · {{ $f->label() }}</p>
        </div>
        <div class="page-actions align-items-center">
            <div wire:loading class="spinner-border spinner-border-sm text-primary"></div>
            <x-date-range-picker :preset="$preset" :from="$from" :to="$to" :live="true" />
            @can('pos.access')<a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> {{ __('Open POS') }}</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Sales')" :value="money($current->total)" icon="bi-cash-stack" :trend="$trend($current->total, $previous->total)" /></div>
        <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Transactions')" :value="number_format($current->count)" icon="bi-receipt" color="info" :trend="$trend((string) $current->count, (string) $previous->count)" /></div>
        <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Avg basket')" :value="money($current->avg)" icon="bi-basket" color="secondary" :trend="$trend($current->avg, $previous->avg)" /></div>
        @if ($showProfit)
            <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Gross profit')" :value="money($current->profit)" icon="bi-graph-up-arrow" color="success" :trend="$trend($current->profit, $previous->profit)" /></div>
        @endif
        <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Cash in drawers')" :value="money($cashInDrawer)" icon="bi-safe" color="warning" :hint="trans_choice(':count open shift|:count open shifts', $shifts->count())" :href="route('shifts.index')" /></div>
        <div class="col-sm-6 col-xl-4 col-xxl-2"><x-stat-card :label="__('Customer debts')" :value="money($debts)" icon="bi-journal-text" color="danger" :hint="trans_choice(':count debtor|:count debtors', $debtors)" :href="\Illuminate\Support\Facades\Route::has('customers.index') && auth()->user()->can('customers.view') ? route('customers.index', ['filters' => ['debt' => 'owing']]) : null" /></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <x-card :title="__('Sales — last 30 days')" icon="bi-graph-up">
                <div class="chart-box" wire:ignore.self><canvas data-chart='@json($trendChart)' wire:key="c-trend-{{ md5(json_encode($trendChart)) }}"></canvas></div>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Payment methods')" icon="bi-credit-card">
                @if (count($methodChart['labels']))
                    <div class="chart-box"><canvas data-chart='@json($methodChart)' wire:key="c-m-{{ md5(json_encode($methodChart)) }}"></canvas></div>
                @else
                    <x-empty-state icon="bi-credit-card" :title="__('No payments in this period')" />
                @endif
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('Top 10 products')" icon="bi-trophy">
                @if (count($topChart['labels']))
                    <div class="chart-box"><canvas data-chart='@json($topChart)' wire:key="c-t-{{ md5(json_encode($topChart)) }}"></canvas></div>
                @else
                    <x-empty-state icon="bi-box-seam" :title="__('No sales in this period')" />
                @endif
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('Sales by hour')" icon="bi-clock">
                <div class="chart-box"><canvas data-chart='@json($hourChart)' wire:key="c-h-{{ md5(json_encode($hourChart)) }}"></canvas></div>
            </x-card>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4 col-lg-6">
            <x-card :title="__('Low stock')" icon="bi-exclamation-triangle" :flush="true">
                <x-slot:actions>@can('purchases.manage')<a href="{{ route('reorder.index') }}" class="btn btn-sm btn-soft-primary">{{ __('Reorder') }}</a>@endcan</x-slot:actions>
                <ul class="list-group list-group-flush">
                    @forelse ($lowStock as $p)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('products.show', $p->id) }}" class="text-decoration-none text-truncate me-2">{{ $p->name }}</a>
                            <span class="badge rounded-pill {{ $p->quantity <= 0 ? 'text-bg-danger-soft' : 'text-bg-warning-soft' }}">{{ qty($p->quantity) }} / {{ qty($p->reorder_level) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small py-4 text-center"><i class="bi bi-emoji-smile"></i> {{ __('All stocked up') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
        <div class="col-xl-4 col-lg-6">
            <x-card :title="__('Expiring within 30 days')" icon="bi-calendar2-x" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse ($expiring as $b)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-truncate me-2">{{ $b->product?->name }} <span class="small text-body-secondary">· {{ $b->batch_no }}</span></span>
                            <x-status-badge :status="$b->expiryStatus()" :label="$b->daysToExpiry() < 0 ? __('Expired') : trans_choice(':count day|:count days', $b->daysToExpiry())" />
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small py-4 text-center">{{ __('Nothing expiring soon') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
        <div class="col-xl-4 col-lg-6">
            <x-card :title="__('Open shifts')" icon="bi-cash-coin" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse ($shifts as $s)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><a href="{{ route('shifts.show', $s) }}" class="text-decoration-none fw-medium">{{ $s->user->name }}</a> <span class="small text-body-secondary">· {{ $s->register->name }} · {{ $s->opened_at->format('H:i') }}</span></span>
                            <span class="fw-semibold text-money">{{ money($s->expected) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small py-4 text-center">{{ __('No open shifts') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
        <div class="col-xl-4 col-lg-6">
            <x-card :title="__('Top customers (30 days)')" icon="bi-star" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse ($topCustomers as $c)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="d-flex align-items-center gap-2 min-w-0"><x-avatar :name="$c->name" size="sm" /><a href="{{ route('customers.show', $c->id) }}" class="text-decoration-none text-truncate">{{ $c->name }}</a></span>
                            <span class="small text-body-secondary text-nowrap">{{ $c->visits }}× · <strong class="text-body">{{ money($c->spent) }}</strong></span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small py-4 text-center">{{ __('No customer sales yet') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
        <div class="col-xl-8">
            <x-card :title="__('Recent sales')" icon="bi-receipt" :flush="true">
                <x-slot:actions><a href="{{ route('sales.index') }}" class="btn btn-sm btn-soft-primary">{{ __('View all') }}</a></x-slot:actions>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 table-stack">
                        <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Time') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $s)
                            <tr>
                                <td data-label="{{ __('Receipt') }}"><a href="{{ route('sales.show', $s) }}" class="font-monospace text-decoration-none">{{ $s->number }}</a></td>
                                <td data-label="{{ __('Time') }}">{{ $s->created_at->diffForHumans() }}</td>
                                <td data-label="{{ __('Customer') }}">{{ $s->customer?->name ?? __('Walk-in') }}</td>
                                <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($s->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No sales yet') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
</div>
