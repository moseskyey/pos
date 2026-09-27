@php
    $steps = ['requested' => __('Requested'), 'approved' => __('Approved'), 'dispatched' => __('Dispatched'), 'received' => __('Received')];
    $current = array_search($transfer->status, array_keys($steps));
@endphp
<x-layouts.app :title="$transfer->number" :breadcrumbs="[__('Transfers') => route('transfers.index'), $transfer->number]">
    <x-page-header :title="$transfer->number">
        <x-slot:meta><div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
            <x-status-badge :status="$transfer->status" />
            <span class="text-body-secondary small"><i class="bi bi-shop"></i> {{ $transfer->fromBranch->name }} <i class="bi bi-arrow-right"></i> {{ $transfer->toBranch->name }}</span>
            @if ($transfer->hasDiscrepancy())<span class="badge rounded-pill text-bg-warning-soft"><i class="bi bi-exclamation-triangle"></i> {{ __('Discrepancy') }}</span>@endif
        </div></x-slot:meta>
        @if (in_array($transfer->status, ['requested', 'approved']))
            <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" data-confirm="{{ __('Cancel this transfer?') }}">@csrf<button class="btn btn-soft-danger">{{ __('Cancel transfer') }}</button></form>
        @endif
        @if ($transfer->status === 'requested')
            @can('stock.transfer.approve')
                <form method="POST" action="{{ route('transfers.approve', $transfer) }}">@csrf<button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Approve') }}</button></form>
            @endcan
        @endif
    </x-page-header>

    {{-- Progress --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between position-relative">
                @foreach ($steps as $key => $label)
                    @php $i = $loop->index; $done = $transfer->status !== 'cancelled' && $current !== false && $i <= $current; @endphp
                    <div class="text-center flex-fill">
                        <div class="rounded-circle d-grid mx-auto mb-1 {{ $done ? 'bg-primary text-white' : 'bg-surface text-body-secondary border' }}" style="width:36px;height:36px;place-items:center">
                            <i class="bi {{ $done ? 'bi-check-lg' : 'bi-circle' }}"></i>
                        </div>
                        <div class="small fw-semibold">{{ $label }}</div>
                        <div class="small text-body-secondary">
                            @switch($key)
                                @case('requested'){{ $transfer->requester?->name }}@break
                                @case('approved'){{ $transfer->approver?->name }}@break
                                @case('dispatched'){{ $transfer->dispatcher?->name }}@break
                                @case('received'){{ $transfer->receiver?->name }}@break
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @php
        $mode = match (true) {
            $transfer->status === 'approved' && $atSource && auth()->user()->can('stock.transfer') => 'dispatch',
            $transfer->status === 'dispatched' && $atDestination && auth()->user()->can('stock.transfer') => 'receive',
            default => null,
        };
    @endphp

    <form method="POST" action="{{ $mode ? route('transfers.'.$mode, $transfer) : '#' }}" @if ($mode) data-confirm="{{ $mode === 'dispatch' ? __('Dispatch? Stock will be deducted from :b.', ['b' => $transfer->fromBranch->name]) : __('Receive into :b stock?', ['b' => $transfer->toBranch->name]) }}" @endif>
        @csrf
        <x-card :title="__('Items')" :flush="true">
            <div class="table-responsive">
                <table class="table align-middle table-stack">
                    <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Requested') }}</th><th class="text-end">{{ __('Dispatched') }}</th><th class="text-end">{{ __('Received') }}</th>@if ($mode)<th style="width:150px">{{ $mode === 'dispatch' ? __('Dispatch qty') : __('Received qty') }}</th>@endif</tr></thead>
                    <tbody>
                    @foreach ($transfer->items as $item)
                        @php $diff = $item->quantity_received !== null && \App\Support\Qty::cmp($item->quantity_received, $item->quantity_dispatched) !== 0; @endphp
                        <tr class="{{ $diff ? 'table-warning' : '' }}">
                            <td data-label="{{ __('Product') }}"><div class="fw-semibold">{{ $item->product->name }}</div>@if ($item->note)<div class="small text-body-secondary">{{ $item->note }}</div>@endif</td>
                            <td data-label="{{ __('Requested') }}" class="text-end">{{ qty($item->quantity_requested) }} {{ $item->product->unit?->short_name }}</td>
                            <td data-label="{{ __('Dispatched') }}" class="text-end">{{ $item->quantity_dispatched !== null ? qty($item->quantity_dispatched) : '—' }}</td>
                            <td data-label="{{ __('Received') }}" class="text-end fw-semibold">{{ $item->quantity_received !== null ? qty($item->quantity_received) : '—' }}</td>
                            @if ($mode)
                                <td data-label="{{ $mode === 'dispatch' ? __('Dispatch qty') : __('Received qty') }}">
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm" name="quantities[{{ $item->id }}]"
                                           value="{{ qty($mode === 'dispatch' ? $item->quantity_requested : $item->quantity_dispatched) }}" @if ($mode === 'receive') max="{{ (float) $item->quantity_dispatched }}" @endif>
                                    @if ($mode === 'receive')<input type="text" class="form-control form-control-sm mt-1" name="notes[{{ $item->id }}]" placeholder="{{ __('Note (e.g. 2 broken)') }}">@endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($mode)
                <x-slot:footer>
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary"><i class="bi {{ $mode === 'dispatch' ? 'bi-truck' : 'bi-box-arrow-in-down' }}"></i> {{ $mode === 'dispatch' ? __('Dispatch now') : __('Receive stock') }}</button>
                    </div>
                </x-slot:footer>
            @endif
        </x-card>
    </form>
    @if ($transfer->note)
        <x-card :title="__('Note')" class="mt-4">{{ $transfer->note }}</x-card>
    @endif
</x-layouts.app>
