@php
    $denoms = config('dukapos.denominations');
    $mine = $shift->user_id === auth()->id();
@endphp
<x-layouts.app :title="$shift->number" :breadcrumbs="[__('Shifts') => route('shifts.index'), $shift->number]">
    <x-page-header :title="__('Shift :n', ['n' => $shift->number])">
        <x-slot:meta><div class="mt-2 d-flex gap-2 align-items-center flex-wrap small text-body-secondary">
            <span class="badge rounded-pill {{ $shift->isOpen() ? 'text-bg-success-soft' : 'text-bg-secondary-soft' }} status-badge">{{ $shift->isOpen() ? __('Open') : __('Closed') }}</span>
            <span><i class="bi bi-person"></i> {{ $shift->user->name }}</span>
            <span><i class="bi bi-pc-display"></i> {{ $shift->register->name }}</span>
            <span><i class="bi bi-clock"></i> {{ format_date($shift->opened_at, true) }} @if ($shift->closed_at) – {{ format_date($shift->closed_at, true) }}@endif</span>
        </div></x-slot:meta>
        <a href="{{ route('shifts.report', [$shift, 'x']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('X report') }}</a>
        @unless ($shift->isOpen())
            <a href="{{ route('shifts.report', [$shift, 'z']) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Z report') }}</a>
        @endunless
        @if ($shift->isOpen() && $mine)
            <a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> {{ __('Back to POS') }}</a>
        @endif
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Sales')" :value="money($summary['net_sales'])" icon="bi-receipt" :hint="trans_choice(':count sale|:count sales', $summary['sales_count'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Opening float')" :value="money($summary['opening_float'])" icon="bi-safe" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Expected cash')" :value="money($summary['expected_cash'])" icon="bi-cash-stack" color="success" /></div>
        <div class="col-6 col-xl-3">
            @if ($shift->isOpen())
                <x-stat-card :label="__('Cash in / out')" :value="money($summary['cash_in']).' / '.money($summary['cash_out'])" icon="bi-arrow-left-right" color="warning" />
            @else
                <x-stat-card :label="__('Over / short')" :value="money($shift->over_short)" icon="bi-scale" :color="$shift->over_short < 0 ? 'danger' : 'success'" :hint="__('Counted :c', ['c' => money($shift->counted_cash)])" />
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <x-card :title="__('Payments by method')" :flush="true">
                <table class="table mb-0">
                    <tbody>
                    @forelse ($summary['payments'] as $method => $p)
                        <tr><td><i class="bi {{ \App\Enums\PaymentMethod::from($method)->icon() }} text-primary"></i> {{ \App\Enums\PaymentMethod::from($method)->label() }} <span class="text-body-secondary small">× {{ $p['count'] }}</span></td><td class="text-end fw-semibold text-money">{{ money($p['total']) }}</td></tr>
                    @empty
                        <tr><td class="text-body-secondary text-center py-4">{{ __('No payments yet') }}</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td>{{ __('Discounts given') }}</td><td class="text-end text-money">{{ money($summary['discounts']) }}</td></tr>
                        <tr><td>{{ __('Voids') }} ({{ $summary['voids']['count'] }})</td><td class="text-end text-money">{{ money($summary['voids']['total']) }}</td></tr>
                        <tr><td>{{ __('Returns') }} ({{ $summary['returns']['count'] }})</td><td class="text-end text-money">{{ money($summary['returns']['total']) }}</td></tr>
                    </tfoot>
                </table>
            </x-card>

            <x-card :title="__('Sales in this shift')" :flush="true" class="mt-4">
                @if ($sales->isEmpty())
                    <x-empty-state icon="bi-receipt" :title="__('No sales yet')" />
                @else
                    <div class="table-responsive" style="max-height: 420px">
                        <table class="table table-hover table-sm table-sticky mb-0">
                            <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Time') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Total') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($sales as $s)
                                <tr>
                                    <td>@if (\Illuminate\Support\Facades\Route::has('sales.show'))<a href="{{ route('sales.show', $s) }}" class="font-monospace text-decoration-none">{{ $s->number }}</a>@else<span class="font-monospace">{{ $s->number }}</span>@endif</td>
                                    <td>{{ $s->created_at->format('H:i') }}</td>
                                    <td>{{ $s->customer?->name ?? '—' }}</td>
                                    <td class="text-end text-money">{{ money($s->total) }}</td>
                                    <td><x-status-badge :status="$s->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-5">
            @if ($shift->isOpen() && ($mine || auth()->user()->can('shifts.manage')))
                @can('cash.movements')
                    <x-card :title="__('Cash in / cash out')" icon="bi-arrow-left-right">
                        <form method="POST" action="{{ route('shifts.cash', $shift) }}" class="row g-2">
                            @csrf
                            <div class="col-5"><x-select name="type" :options="['out' => __('Cash out (paid-out)'), 'in' => __('Cash in')]" class="mb-0" aria-label="{{ __('Type') }}" /></div>
                            <div class="col-7"><x-input name="amount" type="number" min="0" step="50" prefix="TSh" class="mb-0" :placeholder="__('Amount')" required aria-label="{{ __('Amount') }}" /></div>
                            <div class="col-12"><x-input name="reason" :placeholder="__('Reason, e.g. transport, change from bank')" class="mb-0" required aria-label="{{ __('Reason') }}" /></div>
                            <div class="col-12"><button class="btn btn-soft-primary w-100">{{ __('Record') }}</button></div>
                        </form>
                        @if ($shift->cashMovements->isNotEmpty())
                            <ul class="list-group list-group-flush mt-3">
                                @foreach ($shift->cashMovements as $m)
                                    <li class="list-group-item px-0 d-flex justify-content-between small">
                                        <span><i class="bi {{ $m->type === 'in' ? 'bi-arrow-down-circle text-success' : 'bi-arrow-up-circle text-danger' }}"></i> {{ $m->reason }} <span class="text-body-secondary">· {{ $m->created_at->format('H:i') }}</span></span>
                                        <span class="fw-semibold text-money">{{ $m->type === 'in' ? '+' : '−' }}{{ money($m->amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>
                @endcan

                <form method="POST" action="{{ route('shifts.close', $shift) }}" class="mt-4" x-data="denominations(@js($denoms))"
                      data-confirm="{{ $mine ? __('Close your shift now?') : __('Force-close this cashier’s shift? This is logged.') }}" data-confirm-button="{{ __('Close shift') }}">
                    @csrf
                    <x-card :title="$mine ? __('Close shift') : __('Force-close shift')" icon="bi-lock">
                        <p class="small text-body-secondary">{{ __('Count the cash in the drawer. Use the denomination counter or enter the total.') }}</p>
                        <div class="row g-2 mb-3">
                            @foreach ($denoms as $d)
                                <div class="col-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="min-width:78px">{{ number_format($d) }} ×</span>
                                        <input type="number" min="0" class="form-control" name="denominations[{{ $d }}]" x-model.number="counts[{{ $d }}]" aria-label="{{ number_format($d) }}">
                                    </div>
                                </div>
                            @endforeach
                            <div class="col-12">
                                <div class="input-group input-group-sm"><span class="input-group-text">{{ __('Coins / other') }}</span><input type="number" min="0" class="form-control" x-model.number="coins"></div>
                            </div>
                        </div>
                        <label class="form-label required" for="counted_cash">{{ __('Counted cash') }}</label>
                        <div class="input-group input-group-lg mb-2">
                            <span class="input-group-text">TSh</span>
                            <input type="number" min="0" step="0.01" id="counted_cash" name="counted_cash" class="form-control fw-bold" :value="total" required>
                        </div>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-body-secondary">{{ __('Expected') }}: <strong>{{ money($summary['expected_cash']) }}</strong></span>
                            <span :class="total - {{ (float) $summary['expected_cash'] }} < 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold'"
                                  x-text="(total - {{ (float) $summary['expected_cash'] }} < 0 ? '{{ __('Short') }} ' : '{{ __('Over') }} ') + 'TSh ' + Math.abs(total - {{ (float) $summary['expected_cash'] }}).toLocaleString()"></span>
                        </div>
                        <x-textarea name="note" :label="__('Note')" rows="2" />
                        <button class="btn btn-danger w-100"><i class="bi bi-lock"></i> {{ $mine ? __('Close shift') : __('Force-close shift') }}</button>
                    </x-card>
                </form>
            @elseif (! $shift->isOpen())
                <x-card :title="__('Reconciliation')">
                    <dl class="info-list mb-0">
                        <dt>{{ __('Expected cash') }}</dt><dd>{{ money($shift->expected_cash) }}</dd>
                        <dt>{{ __('Counted cash') }}</dt><dd>{{ money($shift->counted_cash) }}</dd>
                        <dt>{{ __('Over / short') }}</dt><dd class="fw-bold {{ $shift->over_short < 0 ? 'text-danger' : 'text-success' }}">{{ money($shift->over_short) }}</dd>
                        <dt>{{ __('Closed by') }}</dt><dd>{{ $shift->closer?->name }} @if ($shift->force_closed)<span class="badge text-bg-warning-soft">{{ __('force-closed') }}</span>@endif</dd>
                        @if ($shift->denominations)
                            <dt>{{ __('Denominations') }}</dt>
                            <dd>@foreach ($shift->denominations as $d => $c)<span class="badge text-bg-secondary-soft me-1">{{ number_format($d) }} × {{ $c }}</span>@endforeach</dd>
                        @endif
                        <dt>{{ __('Note') }}</dt><dd class="mb-0">{{ $shift->note ?: '—' }}</dd>
                    </dl>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
