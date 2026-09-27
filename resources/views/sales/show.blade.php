@php
    $cost = $sale->items->sum(fn ($i) => (float) $i->cost_price * (float) $i->quantity);
    $net = (float) $sale->total - (setting('tax.prices_include_vat') ? (float) $sale->tax_total : 0);
    $net = setting('tax.prices_include_vat') ? $net : (float) $sale->total - (float) $sale->tax_total;
    $profit = $net - $cost;
@endphp
<x-layouts.app :title="$sale->number" :breadcrumbs="[__('Sales') => route('sales.index'), $sale->number]">
    <div class="page-header">
        <div class="min-w-0">
            <h2 class="font-monospace">{{ $sale->number }}</h2>
            <div class="d-flex flex-wrap gap-2 mt-2 align-items-center small text-body-secondary">
                <x-status-badge :status="$sale->status" />
                <span><i class="bi bi-calendar3"></i> {{ format_date($sale->created_at, true) }}</span>
                <span><i class="bi bi-shop"></i> {{ $sale->branch->name }}{{ $sale->register ? ' · '.$sale->register->name : '' }}</span>
                <span><i class="bi bi-person-badge"></i> {{ $sale->cashier?->name }}</span>
                @if ($sale->synced_at)
                    <span class="badge text-bg-info-soft"><i class="bi bi-wifi-off"></i> {{ __('Offline sale · synced :t', ['t' => format_date($sale->synced_at, true)]) }}</span>
                @endif
            </div>
            @if ($sale->review_flags)
                <div class="alert alert-warning small py-2 mt-2 mb-0">
                    <i class="bi bi-flag"></i> <strong>{{ __('Needs review:') }}</strong>
                    {{ collect($sale->review_flags)->map(fn ($f) => __(\App\Models\Sale::REVIEW_FLAGS[$f] ?? \Illuminate\Support\Str::headline($f)))->join(' · ') }}
                </div>
            @endif
        </div>
        <div class="page-actions"><livewire:sales.sale-actions :sale="$sale" /></div>
    </div>

    @if ($sale->status->value === 'voided')
        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-x-octagon"></i>
            <div>{{ __('Voided by :u on :d.', ['u' => $sale->voider?->name, 'd' => format_date($sale->voided_at, true)]) }} <strong>{{ __('Reason') }}:</strong> {{ $sale->void_reason }}</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total')" :value="money($sale->total)" icon="bi-receipt" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Paid')" :value="money($sale->paid_total)" icon="bi-cash-stack" color="success" :hint="$sale->change_due > 0 ? __('Change :c', ['c' => money($sale->change_due)]) : null" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Balance due')" :value="money($sale->balance_due)" icon="bi-hourglass-split" :color="$sale->balance_due > 0 ? 'danger' : 'secondary'" /></div>
        <div class="col-6 col-xl-3">
            @if ($showProfit)
                <x-stat-card :label="__('Gross profit')" :value="money($profit)" icon="bi-graph-up-arrow" color="info" :hint="$net > 0 ? __('Margin :m%', ['m' => round($profit / $net * 100, 1)]) : null" />
            @else
                <x-stat-card :label="__('Items')" :value="qty($sale->items->sum('quantity'))" icon="bi-box-seam" color="info" />
            @endif
        </div>
    </div>

    <ul class="nav nav-tabs-modern mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-items" type="button">{{ __('Items') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-payments" type="button">{{ __('Payments') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-returns" type="button">{{ __('Returns') }} <span class="badge text-bg-secondary-soft">{{ $sale->returns->count() }}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-audit" type="button">{{ __('Audit trail') }}</button></li>
    </ul>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="t-items">
                    <div class="card"><div class="table-responsive">
                        <table class="table table-stack align-middle">
                            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Total') }}</th>@if ($showProfit)<th class="text-end">{{ __('Cost') }}</th>@endif</tr></thead>
                            <tbody>
                            @foreach ($sale->items as $item)
                                <tr>
                                    <td data-label="{{ __('Item') }}">
                                        <a href="{{ route('products.show', $item->product_id) }}" class="fw-semibold text-decoration-none">{{ $item->name }}</a>
                                        <div class="small text-body-secondary">{{ $item->sku }} @if ($item->price_tier !== 'retail')<span class="badge text-bg-info-soft">{{ __(ucfirst($item->price_tier)) }}</span>@endif
                                            @if ($item->returned_quantity > 0)<span class="badge text-bg-warning-soft">{{ __(':q returned', ['q' => qty($item->returned_quantity)]) }}</span>@endif</div>
                                    </td>
                                    <td data-label="{{ __('Qty') }}" class="text-end">{{ qty($item->quantity) }} {{ $item->unit_name }}</td>
                                    <td data-label="{{ __('Price') }}" class="text-end text-money">{{ money($item->unit_price) }}</td>
                                    <td data-label="{{ __('Discount') }}" class="text-end text-money">{{ $item->discount_amount > 0 ? money($item->discount_amount) : '—' }}</td>
                                    <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($item->line_total) }}</td>
                                    @if ($showProfit)<td data-label="{{ __('Cost') }}" class="text-end text-money text-body-secondary">{{ money($item->totalCost()) }}</td>@endif
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="4" class="text-end">{{ __('Subtotal') }}</td><td class="text-end text-money">{{ money($sale->subtotal) }}</td>@if ($showProfit)<td></td>@endif</tr>
                                @if ($sale->discount_total > 0)<tr><td colspan="4" class="text-end">{{ __('Discounts') }}</td><td class="text-end text-money text-success">−{{ money($sale->discount_total) }}</td>@if ($showProfit)<td></td>@endif</tr>@endif
                                <tr><td colspan="4" class="text-end">{{ __('VAT') }}</td><td class="text-end text-money">{{ money($sale->tax_total) }}</td>@if ($showProfit)<td></td>@endif</tr>
                                <tr><td colspan="4" class="text-end fs-6">{{ __('Total') }}</td><td class="text-end text-money fs-6">{{ money($sale->total) }}</td>@if ($showProfit)<td class="text-end text-money">{{ money($cost) }}</td>@endif</tr>
                            </tfoot>
                        </table>
                    </div></div>
                </div>
                <div class="tab-pane fade" id="t-payments">
                    <div class="card">
                        <ul class="list-group list-group-flush">
                            @forelse ($sale->payments as $p)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <div><i class="bi {{ $p->method->icon() }} text-primary"></i> <span class="fw-semibold">{{ $p->method->label() }}</span>
                                        <div class="small text-body-secondary">{{ $p->reference ? __('Ref').': '.$p->reference.' · ' : '' }}{{ format_date($p->created_at, true) }} · {{ $p->receiver?->name }}</div></div>
                                    <span class="fw-bold text-money">{{ money($p->amount) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-body-secondary">{{ __('No payments') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="tab-pane fade" id="t-returns">
                    <div class="card">
                        @forelse ($sale->returns as $r)
                            <div class="p-3 border-bottom d-flex justify-content-between">
                                <div><a href="{{ route('returns.show', $r) }}" class="font-monospace fw-semibold text-decoration-none">{{ $r->number }}</a>
                                    <div class="small text-body-secondary">{{ format_date($r->created_at, true) }} · {{ $r->user?->name }} · {{ $r->reason }} · {{ $r->refundLabel() }}</div></div>
                                <span class="fw-bold text-money text-danger">−{{ money($r->refund_total) }}</span>
                            </div>
                        @empty
                            <x-empty-state icon="bi-arrow-counterclockwise" :title="__('No returns')" />
                        @endforelse
                    </div>
                </div>
                <div class="tab-pane fade" id="t-audit">
                    <div class="card">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item py-3"><i class="bi bi-plus-circle text-success"></i> {{ __('Created by :u', ['u' => $sale->cashier?->name]) }} <span class="small text-body-secondary">· {{ format_date($sale->created_at, true) }}</span></li>
                            @foreach ($activities as $a)
                                <li class="list-group-item py-3"><i class="bi bi-activity text-primary"></i> {{ $a->description }} <span class="small text-body-secondary">· {{ $a->causer?->name ?? __('System') }} · {{ format_date($a->created_at, true) }}</span></li>
                            @endforeach
                            @if ($sale->reprint_count)<li class="list-group-item py-3"><i class="bi bi-printer"></i> {{ trans_choice('Reprinted :count time|Reprinted :count times', $sale->reprint_count) }}</li>@endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Customer')">
                @if ($sale->customer)
                    <div class="d-flex align-items-center gap-3">
                        <x-avatar :name="$sale->customer->name" />
                        <div>
                            <a href="{{ route('customers.show', $sale->customer) }}" class="fw-semibold text-decoration-none">{{ $sale->customer->name }}</a>
                            <div class="small text-body-secondary">{{ $sale->customer->displayPhone() }}</div>
                        </div>
                    </div>
                    @if ($sale->loyalty_earned || $sale->loyalty_redeemed)
                        <div class="small mt-3 text-body-secondary">{{ __('Loyalty: +:e / −:r points', ['e' => $sale->loyalty_earned, 'r' => $sale->loyalty_redeemed]) }}</div>
                    @endif
                @else
                    <span class="text-body-secondary">{{ __('Walk-in customer') }}</span>
                @endif
            </x-card>
            @if ($sale->note)<x-card :title="__('Note')" class="mt-4">{{ $sale->note }}</x-card>@endif
        </div>
    </div>
</x-layouts.app>
