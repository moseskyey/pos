<x-layouts.app :title="$customer->name" :breadcrumbs="[__('Customers') => route('customers.index'), $customer->name]">
    <div class="page-header">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <x-avatar :name="$customer->name" size="lg" />
            <div class="min-w-0">
                <h2 class="text-truncate">{{ $customer->name }}</h2>
                <div class="d-flex flex-wrap gap-2 mt-1 small text-body-secondary align-items-center">
                    <span class="badge rounded-pill text-bg-{{ $customer->isWholesale() ? 'info' : 'secondary' }}-soft">{{ $customer->isWholesale() ? __('Wholesale') : __('Retail') }}</span>
                    <x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" />
                    @if ($customer->phone)<span><i class="bi bi-phone"></i> {{ $customer->displayPhone() }}</span>@endif
                    @if ($customer->email)<span><i class="bi bi-envelope"></i> {{ $customer->email }}</span>@endif
                    @if ($customer->tin)<span>TIN {{ $customer->tin }}</span>@endif
                </div>
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('customers.statement', $customer) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> {{ __('Statement') }}</a>
            @if (feature('whatsapp') && auth()->user()->can('customers.payments'))
                <a href="{{ app(\App\Services\ShareService::class)->statementLink($customer) }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            @endif
            @if ($customer->balance > 0)
                @can('customers.payments')
                    @if ($customer->phone)
                        <form method="POST" action="{{ route('customers.remind', $customer) }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-chat-dots"></i> {{ __('SMS reminder') }}</button></form>
                    @endif
                    <a href="{{ route('customer-payments.create', ['customer' => $customer->id]) }}" class="btn btn-success"><i class="bi bi-cash"></i> {{ __('Receive payment') }}</a>
                @endcan
            @endif
            @can('customers.manage')<a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Balance owed')" :value="money($customer->balance)" icon="bi-journal-text" :color="$customer->balance > $customer->credit_limit ? 'danger' : 'warning'" :hint="__('Limit :l', ['l' => money($customer->credit_limit)])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total spent')" :value="money($stats->spent)" icon="bi-bag-check" :hint="trans_choice(':count visit|:count visits', $stats->visits)" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Store credit')" :value="money($customer->store_credit)" icon="bi-wallet2" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="setting('loyalty.enabled') ? __('Loyalty points') : __('Last visit')" :value="setting('loyalty.enabled') ? number_format($customer->loyalty_points) : ($stats->last_visit ? \Illuminate\Support\Carbon::parse($stats->last_visit)->diffForHumans() : '—')" icon="bi-gift" color="success" /></div>
    </div>

    <ul class="nav nav-tabs-modern mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-overview" type="button">{{ __('Overview') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-ledger" type="button">{{ __('Account ledger') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-sales" type="button">{{ __('Purchases') }}</button></li>
        @if (setting('loyalty.enabled'))<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-loyalty" type="button">{{ __('Loyalty') }}</button></li>@endif
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="t-overview">
            <div class="row g-4">
                <div class="col-lg-6">
                    <x-card :title="__('Debt aging')">
                        @foreach (\App\Services\CustomerStatementService::agingBuckets() as $k => $label)
                            @php $pct = $aging['total'] > 0 ? $aging[$k] / $aging['total'] * 100 : 0; @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small"><span>{{ $label }}</span><span class="fw-semibold text-money">{{ money($aging[$k]) }}</span></div>
                                <div class="progress" style="height:6px"><div class="progress-bar {{ match ($k) { 'current' => 'bg-success', '1_30' => '', '31_60', '61_90' => 'bg-warning', default => 'bg-danger' } }}" style="width: {{ $pct }}%"></div></div>
                            </div>
                        @endforeach
                    </x-card>
                </div>
                <div class="col-lg-6">
                    <x-card :title="__('Favourite products')" :flush="true">
                        @if ($favorites->isEmpty())
                            <x-empty-state icon="bi-heart" :title="__('No purchases yet')" />
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($favorites as $f)
                                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('products.show', $f->product_id) }}" class="text-decoration-none">{{ $f->name }}</a><span class="small text-body-secondary">{{ qty($f->qty) }} · {{ money($f->total) }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                </div>
                @if ($customer->notes)<div class="col-12"><x-card :title="__('Notes')">{{ $customer->notes }}</x-card></div>@endif
            </div>
        </div>
        <div class="tab-pane fade" id="t-ledger">
            <div class="card">
                @if ($ledger->isEmpty())
                    <x-empty-state icon="bi-journal" :title="__('No account activity yet')" />
                @else
                    <div class="table-responsive"><table class="table table-stack table-sm align-middle">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                        <tbody>
                        @foreach ($ledger as $e)
                            <tr>
                                <td data-label="{{ __('Date') }}">{{ format_date($e->created_at, true) }}</td>
                                <td data-label="{{ __('Account') }}"><span class="badge text-bg-{{ $e->account === 'store_credit' ? 'info' : 'secondary' }}-soft">{{ $e->account === 'store_credit' ? __('Store credit') : __('Receivable') }}</span></td>
                                <td data-label="{{ __('Description') }}">{{ $e->note ?: \Illuminate\Support\Str::headline($e->type) }}</td>
                                <td data-label="{{ __('Debit') }}" class="text-end text-money">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td data-label="{{ __('Credit') }}" class="text-end text-money text-success">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                                <td data-label="{{ __('Balance') }}" class="text-end text-money fw-semibold">{{ money($e->balance_after) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
        <div class="tab-pane fade" id="t-sales">
            <livewire:tables.sales-table :customer-id="$customer->id" />
        </div>
        @if (setting('loyalty.enabled'))
            <div class="tab-pane fade" id="t-loyalty">
                <div class="card">
                    <ul class="list-group list-group-flush">
                        @forelse ($loyalty as $t)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $t->note }} <span class="small text-body-secondary">· {{ format_date($t->created_at, true) }}</span></span>
                                <span class="fw-semibold {{ $t->points < 0 ? 'text-danger' : 'text-success' }}">{{ $t->points > 0 ? '+' : '' }}{{ $t->points }}</span></li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No points yet') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
