@php
    $labels = \App\Models\Platform\Tenant::statusLabels() + ['deleted' => __('Deleted')];
    $color = $status === 'deleted' ? 'danger' : \App\Models\Platform\Tenant::statusColor($status);
    $endsAt = $tenant->accessEndsAt();
@endphp
<x-layouts.admin :title="$tenant->name" :breadcrumbs="[__('Businesses') => route('admin.tenants.index'), $tenant->name]">
    <x-page-header :title="$tenant->name" :subtitle="'#'.$tenant->id.' · '.$tenant->slug.' · '.__('since :date', ['date' => $tenant->created_at->format('d/m/Y')])">
        <x-slot:meta><span class="badge rounded-pill text-bg-{{ $color }}-soft status-badge mt-2">{{ $labels[$status] }}</span></x-slot:meta>
        @if ($tenant->trashed())
            <form method="POST" action="{{ route('admin.tenants.restore', $tenant) }}">@csrf<button class="btn btn-primary"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore') }}</button></form>
            @if (auth('admin')->user()->is_super)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeModal"><i class="bi bi-trash3"></i> {{ __('Delete permanently') }}</button>
            @endif
        @else
            <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" data-confirm="{{ __('Open :name as its owner? This is logged in both activity logs.', ['name' => $tenant->name]) }}">@csrf
                <button class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Login as owner') }}</button>
            </form>
            <a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
            <div class="dropdown">
                <button class="btn btn-light" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More actions') }}"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($tenant->suspended_at)
                        <li><form method="POST" action="{{ route('admin.tenants.unsuspend', $tenant) }}">@csrf<button class="dropdown-item"><i class="bi bi-play-circle"></i> {{ __('Restore access') }}</button></form></li>
                    @else
                        <li><button class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#suspendModal"><i class="bi bi-pause-circle"></i> {{ __('Suspend') }}</button></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" data-confirm="{{ __('Delete :name? Its users can no longer sign in. The data is kept and the business can be restored.', ['name' => $tenant->name]) }}">@csrf @method('DELETE')
                        <button class="dropdown-item text-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button></form></li>
                </ul>
            </div>
        @endif
    </x-page-header>

    @include('admin.partials.generated-password')
    @if ($tenant->suspended_at)
        <div class="alert alert-secondary"><i class="bi bi-slash-circle"></i> {{ __('Suspended on :date: :reason', ['date' => $tenant->suspended_at->format('d/m/Y'), 'reason' => $tenant->suspension_reason]) }}</div>
    @endif
    @if ($usageError)
        <div class="alert alert-danger"><i class="bi bi-database-exclamation"></i> {{ $usageError }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Plan')" :value="$tenant->plan?->name ?? __('No plan')" icon="bi-stars" color="primary" :hint="$tenant->plan ? money($tenant->plan->price).' / '.$tenant->plan->intervalLabel() : null" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="$status === 'trial' ? __('Trial ends') : __('Access until')" :value="$endsAt?->format('d/m/Y') ?? '—'" icon="bi-calendar-check" :color="$color" :hint="$endsAt ? trans_choice(':count day left|:count days left', $tenant->daysLeft()) : null" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Total paid')" :value="money($totalPaid)" icon="bi-cash-stack" color="success" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Last active')" :value="$tenant->last_activity_at?->diffForHumans(short: true) ?? __('Never')" icon="bi-activity" color="info" :hint="$tenant->last_activity_at?->format('d/m/Y H:i')" /></div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist" x-data x-init="const h = location.hash && document.querySelector('[data-bs-target=\'' + location.hash + '-pane\']'); if (h) bootstrap.Tab.getOrCreateInstance(h).show()">
        @foreach (['overview' => [__('Overview'), 'bi-grid'], 'subscription' => [__('Subscription'), 'bi-credit-card'], 'payments' => [__('Payments'), 'bi-receipt'], 'users' => [__('Users'), 'bi-people'], 'activity' => [__('Activity'), 'bi-clock-history']] as $key => [$label, $icon])
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $key }}-pane" type="button" role="tab" aria-controls="{{ $key }}-pane" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        @click="history.replaceState(null, '', '#{{ $key }}')">
                    <i class="bi {{ $icon }}"></i> {{ $label }}
                    @if ($key === 'users')<span class="badge rounded-pill text-bg-light ms-1">{{ $users->count() }}</span>@endif
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        {{-- Overview --}}
        <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" tabindex="0">
            <div class="row g-3">
                <div class="col-lg-6">
                    <x-card :title="__('Business & owner')" icon="bi-shop">
                        <dl class="row mb-0 info-list">
                            <dt class="col-5">{{ __('Owner') }}</dt><dd class="col-7">{{ $tenant->owner_name ?? '—' }}</dd>
                            <dt class="col-5">{{ __('Email') }}</dt><dd class="col-7">@if ($tenant->owner_email)<a href="mailto:{{ $tenant->owner_email }}">{{ $tenant->owner_email }}</a>@else — @endif</dd>
                            <dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7">@if ($tenant->owner_phone)<a href="tel:+{{ $tenant->owner_phone }}">{{ \App\Support\PhoneNumber::display($tenant->owner_phone) }}</a> · <a href="https://wa.me/{{ $tenant->owner_phone }}" target="_blank" rel="noopener" class="text-success"><i class="bi bi-whatsapp"></i></a>@else — @endif</dd>
                            <dt class="col-5">{{ __('Database') }}</dt><dd class="col-7 font-monospace small">{{ $tenant->database }}</dd>
                            <dt class="col-5">{{ __('Signed up') }}</dt><dd class="col-7">{{ $tenant->created_at->format('d/m/Y H:i') }}</dd>
                            <dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7 mb-0" style="white-space: pre-line">{{ $tenant->notes ?: '—' }}</dd>
                        </dl>
                    </x-card>
                </div>
                <div class="col-lg-6">
                    <x-card :title="__('Usage')" icon="bi-speedometer2">
                        @if ($usage)
                            @foreach (['branches' => __('Branches'), 'users' => __('Active users'), 'products' => __('Products')] as $key => $label)
                                @php $limit = $tenant->plan?->limit($key); $pct = $limit ? min(100, round($usage[$key] / max(1, $limit) * 100)) : 0; @endphp
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1"><span>{{ $label }}</span><span class="fw-semibold">{{ number_format($usage[$key]) }} / {{ $limit ? number_format($limit) : '∞' }}</span></div>
                                    <div class="progress" style="height:6px" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-{{ $pct >= 90 ? 'danger' : 'primary' }}" style="width: {{ $pct }}%"></div></div>
                                </div>
                            @endforeach
                            <dl class="row mb-0 info-list">
                                <dt class="col-6">{{ __('Sales, last 30 days') }}</dt><dd class="col-6">{{ number_format($usage['sales_30']) }} · {{ money($usage['revenue_30']) }}</dd>
                                <dt class="col-6">{{ __('Last sale') }}</dt><dd class="col-6 mb-0">{{ $usage['last_sale'] ? \Illuminate\Support\Carbon::parse($usage['last_sale'])->diffForHumans() : __('None yet') }}</dd>
                            </dl>
                        @else
                            <x-empty-state icon="bi-database-x" :title="__('Usage unavailable')" />
                        @endif
                    </x-card>
                </div>
            </div>
        </div>

        {{-- Subscription actions --}}
        <div class="tab-pane fade" id="subscription-pane" role="tabpanel" tabindex="0">
            @if ($tenant->trashed())
                <x-empty-state icon="bi-trash" :title="__('This business is deleted')" :message="__('Restore it to change its subscription.')" />
            @else
                <div class="row g-3">
                    <div class="col-lg-6">
                        <x-card :title="__('Record a payment')" icon="bi-cash-coin" :subtitle="__('Cash, bank transfer or mobile money received outside the app.')">
                            <form method="POST" action="{{ route('admin.tenants.payments.store', $tenant) }}" x-data="{ plans: @js($plans->mapWithKeys(fn ($p) => [$p->id => (float) $p->price])), plan: '{{ $tenant->plan_id ?? $plans->first()?->id }}', periods: 1, get amount() { return (this.plans[this.plan] || 0) * this.periods } }">
                                @csrf
                                <div class="row g-2">
                                    <div class="col-md-7">
                                        <label class="form-label required" for="rp_plan">{{ __('Plan') }}</label>
                                        <select id="rp_plan" name="plan_id" class="form-select" x-model="plan" required>
                                            @foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ money($p->price) }} / {{ $p->intervalLabel() }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label required" for="rp_periods">{{ __('Periods') }}</label>
                                        <input id="rp_periods" type="number" name="periods" min="1" max="24" class="form-control" x-model.number="periods" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required" for="rp_amount">{{ __('Amount received') }}</label>
                                        <input id="rp_amount" type="number" name="amount" min="0" step="0.01" class="form-control" :value="amount" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required" for="rp_method">{{ __('Method') }}</label>
                                        <select id="rp_method" name="method" class="form-select" required>
                                            @foreach (['mobile', 'cash', 'bank', 'complimentary'] as $m)<option value="{{ $m }}">{{ \App\Models\Platform\SubscriptionPayment::methodLabels()[$m] }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6"><x-input name="reference" :label="__('Reference')" placeholder="SGH7K2L9QX" class="mb-0" /></div>
                                    <div class="col-md-6"><x-input name="paid_at" type="date" :label="__('Date received')" :value="now()->toDateString()" class="mb-0" /></div>
                                    <div class="col-12"><x-input name="note" :label="__('Note')" class="mb-0" /></div>
                                </div>
                                <button class="btn btn-success mt-3"><i class="bi bi-check2-circle"></i> {{ __('Record payment & extend') }}</button>
                            </form>
                        </x-card>
                    </div>
                    <div class="col-lg-6">
                        <x-card :title="__('Extend access')" icon="bi-calendar-plus" :subtitle="__('Free days for support or goodwill. Extends the trial if the business is on trial.')" class="mb-3">
                            <form method="POST" action="{{ route('admin.tenants.extend', $tenant) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-sm-4"><x-input name="days" type="number" min="1" max="3650" :label="__('Days')" value="7" required class="mb-0" /></div>
                                <div class="col-sm-8"><x-input name="reason" :label="__('Reason')" class="mb-0" /></div>
                                <div class="col-12"><button class="btn btn-outline-primary"><i class="bi bi-calendar-plus"></i> {{ __('Extend') }}</button></div>
                            </form>
                        </x-card>
                        <x-card :title="__('Change plan')" icon="bi-arrow-left-right" :subtitle="__('Takes effect now. Limits apply immediately; the paid-until date does not change.')">
                            <form method="POST" action="{{ route('admin.tenants.plan', $tenant) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-sm-8">
                                    <label class="form-label" for="cp_plan">{{ __('Plan') }}</label>
                                    <select id="cp_plan" name="plan_id" class="form-select">
                                        <option value="">{{ __('No plan (no limits)') }}</option>
                                        @foreach ($plans as $p)<option value="{{ $p->id }}" @selected($tenant->plan_id === $p->id)>{{ $p->name }}{{ $p->is_active ? '' : ' ('.__('inactive').')' }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4"><button class="btn btn-outline-primary w-100">{{ __('Change') }}</button></div>
                            </form>
                        </x-card>
                    </div>
                </div>
            @endif
        </div>

        {{-- Payments --}}
        <div class="tab-pane fade" id="payments-pane" role="tabpanel" tabindex="0">
            <livewire:admin.payments-table :tenant-id="$tenant->id" />
        </div>

        {{-- Users --}}
        <div class="tab-pane fade" id="users-pane" role="tabpanel" tabindex="0">
            <x-card :flush="true">
                @if ($users->isEmpty())
                    <x-empty-state icon="bi-people" :title="__('No users')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>{{ __('User') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Role') }}</th><th>{{ __('Last sign in') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                            <tbody>
                            @foreach ($users as $u)
                                <tr>
                                    <td><div class="fw-semibold">{{ $u->name }}</div><div class="small text-body-secondary">{{ $u->email }}</div></td>
                                    <td class="small">{{ $u->phone ? \App\Support\PhoneNumber::display($u->phone) : '—' }}</td>
                                    <td>{{ $u->roleLabel() }}</td>
                                    <td class="small text-nowrap">{{ $u->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                                    <td>
                                        @if ($u->trashed())<x-status-badge status="deleted" :label="__('Deleted')" />
                                        @else<x-status-badge :status="$u->is_active ? 'active' : 'inactive'" />@endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @unless ($u->trashed() || $tenant->trashed())
                                            <form method="POST" action="{{ route('admin.tenants.users.password', [$tenant, $u->id]) }}" class="d-inline" data-confirm="{{ __('Reset the password for :name? A new password is shown once.', ['name' => $u->name]) }}">@csrf
                                                <button class="btn btn-sm btn-light" title="{{ __('Reset password') }}"><i class="bi bi-key"></i> <span class="d-none d-xl-inline">{{ __('Reset password') }}</span></button></form>
                                            <form method="POST" action="{{ route('admin.tenants.users.toggle', [$tenant, $u->id]) }}" class="d-inline">@csrf
                                                <button class="btn btn-sm btn-light {{ $u->is_active ? 'text-danger' : 'text-success' }}" title="{{ $u->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $u->is_active ? __('Deactivate') : __('Activate') }}"><i class="bi {{ $u->is_active ? 'bi-person-dash' : 'bi-person-check' }}"></i></button></form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Activity --}}
        <div class="tab-pane fade" id="activity-pane" role="tabpanel" tabindex="0">
            <livewire:admin.activity-table :tenant-id="$tenant->id" />
        </div>
    </div>

    @push('modals')
        <x-modal id="suspendModal" :title="__('Suspend :name', ['name' => $tenant->name])">
            <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" id="suspendForm">@csrf
                <p class="small text-body-secondary">{{ __('All users of this business are blocked from signing in until you restore access. No data is deleted.') }}</p>
                <x-input name="reason" :label="__('Reason (shown to the business)')" required maxlength="255" class="mb-0" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" form="suspendForm" class="btn btn-warning">{{ __('Suspend') }}</button>
            </x-slot:footer>
        </x-modal>
        @if ($tenant->trashed())
            <x-modal id="purgeModal" :title="__('Delete permanently')">
                <form method="POST" action="{{ route('admin.tenants.purge', $tenant) }}" id="purgeForm">@csrf @method('DELETE')
                    <div class="alert alert-danger small">{{ __('This drops the business database and its files. Sales, stock and customers are gone for good. This cannot be undone.') }}</div>
                    <x-input name="confirm" :label="__('Type :slug to confirm', ['slug' => $tenant->slug])" required autocomplete="off" class="mb-0" />
                </form>
                <x-slot:footer>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" form="purgeForm" class="btn btn-danger">{{ __('Delete permanently') }}</button>
                </x-slot:footer>
            </x-modal>
        @endif
        @include('admin.partials.refund-modal')
    @endpush
</x-layouts.admin>
