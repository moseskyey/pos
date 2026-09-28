<x-layouts.app :title="$card->displayCode()" :breadcrumbs="[__('Gift cards') => route('gift-cards.index'), $card->displayCode()]">
    <div class="page-header">
        <div class="min-w-0">
            <h2 class="font-monospace">{{ $card->displayCode() }}</h2>
            <div class="d-flex flex-wrap gap-2 mt-2 align-items-center small text-body-secondary">
                <x-status-badge :status="$card->status()" />
                <span><i class="bi bi-gift"></i> {{ $card->kind === 'voucher' ? __('Voucher') : __('Gift card') }}</span>
                <span><i class="bi bi-shop"></i> {{ __('Issued at :b', ['b' => $card->branch?->name]) }}</span>
                @if ($card->customer)<span><i class="bi bi-person"></i> <a href="{{ route('customers.show', $card->customer) }}">{{ $card->customer->name }}</a></span>@endif
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('gift-cards.print', $card) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print card') }}</a>
            <form method="POST" action="{{ route('gift-cards.toggle', $card) }}" @if ($card->is_active) data-confirm="{{ __('Deactivate this card? It can no longer be spent.') }}" @endif>@csrf
                <button class="btn {{ $card->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"><i class="bi bi-power"></i> {{ $card->is_active ? __('Deactivate') : __('Reactivate') }}</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Balance')" :value="money($card->balance)" icon="bi-wallet2" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Original value')" :value="money($card->initial_value)" icon="bi-gift" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Spent')" :value="money(\App\Support\Money::sub($card->initial_value, $card->balance))" icon="bi-bag-check" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Expires')" :value="$card->expires_on ? format_date($card->expires_on) : __('Never')" icon="bi-calendar-x" :color="$card->isExpired() ? 'danger' : 'secondary'" /></div>
    </div>

    <x-card :title="__('History')" icon="bi-clock-history" flush>
        @if ($card->transactions->isEmpty())
            <x-empty-state icon="bi-clock-history" :title="__('No activity yet')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-stack">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Sale') }}</th><th>{{ __('Branch') }}</th><th>{{ __('By') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                    <tbody>
                        @foreach ($card->transactions as $t)
                            <tr>
                                <td data-label="{{ __('Date') }}" class="text-nowrap">{{ format_date($t->created_at, true) }}</td>
                                <td data-label="{{ __('Type') }}">{{ ['issue' => __('Issued'), 'redeem' => __('Spent'), 'void' => __('Refunded (void)')][$t->type] ?? $t->type }}
                                    @if ($t->payment_method)<span class="small text-body-secondary">· {{ \App\Enums\PaymentMethod::tryFrom($t->payment_method)?->label() }}</span>@endif</td>
                                <td data-label="{{ __('Sale') }}">@if ($t->sale)<a href="{{ route('sales.show', $t->sale) }}" class="font-monospace">{{ $t->sale->number }}</a>@else — @endif</td>
                                <td data-label="{{ __('Branch') }}">{{ $t->branch?->name }}</td>
                                <td data-label="{{ __('By') }}">{{ $t->user?->name ?? '—' }}</td>
                                <td data-label="{{ __('Amount') }}" class="text-end text-money {{ $t->amount < 0 ? 'text-danger' : 'text-success' }}">{{ money($t->amount) }}</td>
                                <td data-label="{{ __('Balance') }}" class="text-end text-money">{{ money($t->balance_after) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    @if ($card->note)<p class="small text-body-secondary mt-3"><i class="bi bi-sticky"></i> {{ $card->note }}</p>@endif
</x-layouts.app>
