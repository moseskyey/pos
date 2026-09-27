<x-layouts.app :title="$return->number" :breadcrumbs="[__('Returns') => route('returns.index'), $return->number]">
    <x-page-header :title="$return->number">
        <x-slot:meta><div class="small text-body-secondary mt-2">
            {{ format_date($return->created_at, true) }} · {{ $return->user?->name }}@if ($return->approver) · {{ __('approved by :a', ['a' => $return->approver->name]) }}@endif
            · {{ __('Sale') }} <a href="{{ route('sales.show', $return->sale_id) }}" class="font-monospace">{{ $return->sale?->number }}</a>
        </div></x-slot:meta>
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card :label="__('Refund')" :value="money($return->refund_total)" icon="bi-cash-coin" color="danger" :hint="$return->refundLabel().($return->refund_reference ? ' · '.$return->refund_reference : '')" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Customer')" :value="$return->customer?->name ?? __('Walk-in')" icon="bi-person" color="info" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Reason')" :value="$return->reason" icon="bi-chat-left-text" color="secondary" /></div>
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit refund') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Condition') }}</th></tr></thead>
            <tbody>
            @foreach ($return->items as $item)
                <tr>
                    <td data-label="{{ __('Item') }}" class="fw-semibold">{{ $item->product?->name }}</td>
                    <td data-label="{{ __('Qty') }}" class="text-end">{{ qty($item->quantity) }}</td>
                    <td data-label="{{ __('Unit refund') }}" class="text-end text-money">{{ money($item->unit_price) }}</td>
                    <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($item->line_total) }}</td>
                    <td data-label="{{ __('Condition') }}"><span class="badge rounded-pill text-bg-{{ $item->condition === 'damaged' ? 'danger' : 'success' }}-soft">{{ $item->condition === 'damaged' ? __('Damaged') : __('Restocked') }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</x-layouts.app>
