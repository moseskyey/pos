@php
    $statusLabels = \App\Models\Platform\Tenant::statusLabels();
    $endsAt = $tenant->accessEndsAt();
    $limits = ['branches' => [__('Branches'), 'bi-shop'], 'users' => [__('Active users'), 'bi-people'], 'products' => [__('Products'), 'bi-box-seam']];
    $planPrices = $plans->mapWithKeys(fn ($p) => [$p->id => ['price' => (float) $p->price, 'months' => $p->interval_months, 'name' => $p->name]]);
@endphp
<x-layouts.app :title="__('Subscription')" :breadcrumbs="[__('Settings') => route('settings.edit'), __('Subscription')]">
    <x-page-header :title="__('Subscription & billing')" :subtitle="$tenant->name">
        <x-status-badge :status="$status" :label="$statusLabels[$status]" class="fs-6" />
    </x-page-header>

    @if ($status === 'suspended')
        <div class="alert alert-secondary d-flex gap-2" role="alert"><i class="bi bi-slash-circle fs-4"></i>
            <div><strong>{{ __('This business account is suspended.') }}</strong><br>{{ $tenant->suspension_reason ?: __('Please contact support to restore access.') }}</div></div>
    @elseif ($status === 'expired')
        <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon fs-4"></i>
            <div><strong>{{ __('Your subscription has expired.') }}</strong><br>{{ $canPay ? __('Renew below to continue using DukaPOS. Your data is safe and nothing has been deleted.') : __('Ask the business owner to renew the subscription. Your data is safe.') }}</div></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Status')" :value="$statusLabels[$status]" icon="bi-shield-check" :color="\App\Models\Platform\Tenant::statusColor($status)" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Plan')" :value="$tenant->plan?->name ?? __('Free trial')" icon="bi-stars" color="primary" :hint="$tenant->plan ? money($tenant->plan->price).' / '.$tenant->plan->intervalLabel() : null" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="$status === 'trial' ? __('Trial ends') : __('Paid until')" :value="$endsAt?->format('d/m/Y') ?? '—'" icon="bi-calendar-check" color="info" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Days left')" :value="number_format($tenant->daysLeft())" icon="bi-hourglass-split" :color="$tenant->daysLeft() <= 7 ? 'warning' : 'success'" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            @if ($canPay && $status !== 'suspended')
                <x-card :title="__('Renew or change plan')" icon="bi-credit-card-2-front" class="mb-3">
                    @if ($plans->isEmpty())
                        <x-empty-state icon="bi-stars" :title="__('No plans available')" :message="__('Please contact support to renew.')" />
                    @elseif ($pushAvailable)
                        <div x-data="billingPay(@js([
                                'plans' => $planPrices,
                                'plan' => (string) old('plan_id', $tenant->plan_id ?? $plans->first()->id),
                                'periods' => (int) old('periods', 1),
                                'statusUrl' => $pending ? route('billing.status', $pending) : null,
                                'symbol' => setting('currency.symbol', 'TSh'),
                            ]))">
                            <div class="text-center py-4" x-show="waiting" x-cloak role="status" aria-live="polite">
                                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem"></div>
                                <h3 class="h5 fw-bold">{{ __('Waiting for your payment…') }}</h3>
                                <p class="text-body-secondary mb-0">{{ __('Enter your mobile money PIN on your phone. This page updates by itself.') }}</p>
                                <p class="text-danger small mt-2" x-show="message" x-text="message"></p>
                            </div>
                            <form method="POST" action="{{ route('billing.pay') }}" x-show="!waiting" @submit="loading = true" novalidate>
                                @csrf
                                <fieldset class="mb-3">
                                    <legend class="form-label fs-6 required">{{ __('Plan') }}</legend>
                                    <div class="row g-2">
                                        @foreach ($plans as $plan)
                                            <div class="col-md-6">
                                                <label class="card h-100 mb-0 plan-option" :class="plan === '{{ $plan->id }}' ? 'border-primary shadow-sm' : ''">
                                                    <span class="card-body d-flex gap-2">
                                                        <input class="form-check-input mt-1" type="radio" name="plan_id" value="{{ $plan->id }}" x-model="plan">
                                                        <span class="flex-grow-1 min-w-0">
                                                            <span class="d-flex justify-content-between gap-2"><span class="fw-semibold">{{ $plan->name }}</span>
                                                                @if ($tenant->plan_id === $plan->id)<span class="badge rounded-pill text-bg-primary-soft">{{ __('Current') }}</span>@endif</span>
                                                            <span class="d-block fw-bold fs-5">{{ money($plan->price) }} <small class="fw-normal text-body-secondary fs-6">/ {{ $plan->intervalLabel() }}</small></span>
                                                            @if ($plan->description)<span class="d-block small text-body-secondary">{{ $plan->description }}</span>@endif
                                                            <span class="d-block small text-body-secondary mt-1">
                                                                {{ $plan->max_branches ? trans_choice(':count branch|:count branches', $plan->max_branches) : __('Unlimited branches') }} ·
                                                                {{ $plan->max_users ? trans_choice(':count user|:count users', $plan->max_users) : __('Unlimited users') }} ·
                                                                {{ $plan->max_products ? __(':count products', ['count' => number_format($plan->max_products)]) : __('Unlimited products') }}
                                                            </span>
                                                        </span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('plan_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </fieldset>
                                <div class="row g-2 align-items-end">
                                    <div class="col-sm-4">
                                        <label for="periods" class="form-label required">{{ __('Pay for') }}</label>
                                        <select id="periods" name="periods" class="form-select" x-model.number="periods">
                                            @foreach ([1, 2, 3, 6, 12] as $n)
                                                <option value="{{ $n }}">{{ trans_choice(':count period|:count periods', $n) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-8">
                                        <x-input name="phone" type="tel" :label="__('Mobile money number')" :value="old('phone', $tenant->owner_phone ? '0'.substr($tenant->owner_phone, 3) : '')" required class="mb-0"
                                                 placeholder="0712 345 678" prefix="<i class='bi bi-phone'></i>" :help="__('M-Pesa, Mixx by Yas, Airtel Money or HaloPesa')" />
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                                    <div>
                                        <div class="small text-body-secondary" x-text="summary()"></div>
                                        <div class="fs-3 fw-bold text-money" x-text="total()"></div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-lg" :disabled="loading">
                                        <span class="spinner-border spinner-border-sm" x-show="loading" x-cloak></span>
                                        <i class="bi bi-phone-vibrate" x-show="!loading"></i> {{ __('Pay with mobile money') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="row g-2 mb-3">
                            @foreach ($plans as $plan)
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100 {{ $tenant->plan_id === $plan->id ? 'border-primary' : '' }}">
                                        <div class="d-flex justify-content-between gap-2"><span class="fw-semibold">{{ $plan->name }}</span>
                                            @if ($tenant->plan_id === $plan->id)<span class="badge rounded-pill text-bg-primary-soft">{{ __('Current') }}</span>@endif</div>
                                        <div class="fw-bold fs-5">{{ money($plan->price) }} <small class="fw-normal text-body-secondary fs-6">/ {{ $plan->intervalLabel() }}</small></div>
                                        @if ($plan->description)<div class="small text-body-secondary">{{ $plan->description }}</div>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($support['instructions'])
                            <p class="mb-2">{{ __('To renew, pay using the details below and send the confirmation to our support team. Your account is extended as soon as the payment is confirmed.') }}</p>
                            <div class="p-3 rounded-3 bg-body-tertiary small" style="white-space: pre-line">{{ $support['instructions'] }}</div>
                        @else
                            <p class="mb-0">{{ __('To renew or change your plan, contact our support team (details on the right). Your account is extended as soon as the payment is confirmed.') }}</p>
                        @endif
                    @endif
                </x-card>
            @endif

            <x-card :title="__('Payment history')" icon="bi-receipt" :flush="true">
                @if ($payments->isEmpty())
                    <x-empty-state icon="bi-receipt" :title="__('No payments yet')" :message="__('Your subscription payments and invoices will appear here.')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Period') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($payments as $p)
                                <tr>
                                    <td class="text-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('d/m/Y') }}</td>
                                    <td class="font-monospace small">{{ $p->number ?? '—' }}</td>
                                    <td>{{ $p->plan?->name ?? '—' }}</td>
                                    <td class="small text-nowrap">{{ $p->period_start ? $p->period_start->format('d/m/Y').' – '.$p->period_end->format('d/m/Y') : trans_choice(':count month|:count months', $p->months) }}</td>
                                    <td class="small">{{ \App\Models\Platform\SubscriptionPayment::methodLabels()[$p->method] ?? $p->method }}</td>
                                    <td class="text-end text-nowrap"><x-money :amount="$p->amount" /></td>
                                    <td><x-status-badge :status="$p->status" /></td>
                                    <td class="text-end">
                                        @if (in_array($p->status, ['completed', 'refunded'], true))
                                            <a href="{{ route('billing.invoice', $p) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Invoice PDF') }}" aria-label="{{ __('Invoice PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card :title="__('Plan usage')" icon="bi-speedometer2" class="mb-3">
                @foreach ($limits as $key => [$label, $icon])
                    @php $limit = $tenant->plan?->limit($key); $used = $usage[$key]; $pct = $limit ? min(100, round($used / max(1, $limit) * 100)) : 0; @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="bi {{ $icon }} text-body-secondary"></i> {{ $label }}</span>
                            <span class="fw-semibold">{{ number_format($used) }} / {{ $limit ? number_format($limit) : '∞' }}</span>
                        </div>
                        <div class="progress" style="height:6px" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-{{ $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'primary') }}" style="width: {{ $limit ? $pct : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </x-card>
            <x-card :title="__('Need help?')" icon="bi-headset">
                <p class="small text-body-secondary">{{ __('Questions about your subscription or a payment? Contact us.') }}</p>
                <ul class="list-unstyled small mb-0 d-grid gap-2">
                    @if ($support['phone'])<li><i class="bi bi-telephone text-primary"></i> <a href="tel:{{ $support['phone'] }}">{{ $support['phone'] }}</a></li>@endif
                    @if ($support['whatsapp'])<li><i class="bi bi-whatsapp text-success"></i> <a href="https://wa.me/{{ preg_replace('/\D/', '', $support['whatsapp']) }}" target="_blank" rel="noopener">{{ $support['whatsapp'] }}</a></li>@endif
                    @if ($support['email'])<li><i class="bi bi-envelope text-primary"></i> <a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a></li>@endif
                    @if (! $support['phone'] && ! $support['whatsapp'] && ! $support['email'])<li class="text-body-secondary">{{ __('Support contacts have not been set up yet.') }}</li>@endif
                </ul>
                @if ($pushAvailable && $support['instructions'])
                    <hr><div class="small text-body-secondary" style="white-space: pre-line"><strong>{{ __('Other ways to pay') }}</strong><br>{{ $support['instructions'] }}</div>
                @endif
            </x-card>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('billingPay', (cfg) => ({
                ...cfg,
                loading: false,
                waiting: !!cfg.statusUrl,
                message: '',
                init() { if (this.waiting) this.poll(); },
                current() { return this.plans[this.plan] || { price: 0, months: 1, name: '' }; },
                total() { return this.symbol + ' ' + (this.current().price * this.periods).toLocaleString(); },
                summary() { const m = this.current().months * this.periods; return this.current().name + ' · ' + m + ' ' + (m === 1 ? @js(__('month')) : @js(__('months'))); },
                async poll() {
                    for (let i = 0; i < 60; i++) {
                        await new Promise((r) => setTimeout(r, 5000));
                        try {
                            const res = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                            if (!res.ok) continue;
                            const data = await res.json();
                            if (data.status === 'completed') { window.location.reload(); return; }
                            if (data.status === 'failed') { this.message = data.message || @js(__('The payment did not go through. You can try again.')); this.waiting = false; return; }
                        } catch (e) { /* offline for a moment: keep polling */ }
                    }
                    this.waiting = false;
                    this.message = @js(__('No confirmation yet. If you paid, your account will be extended automatically within a few minutes.'));
                },
            }));
        });
    </script>
    @endpush
</x-layouts.app>
