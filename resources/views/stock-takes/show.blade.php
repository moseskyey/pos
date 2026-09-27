<x-layouts.app :title="$take->number" :breadcrumbs="[__('Stock takes') => route('stock-takes.index'), $take->number]">
    <x-page-header :title="$take->number">
        <x-slot:meta><div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
            <x-status-badge :status="$take->status" />
            <span class="small text-body-secondary">{{ $take->branch->name }} · {{ $take->category?->name ?? __('Full count') }} · {{ __('Frozen') }} {{ format_date($take->frozen_at, true) }}</span>
        </div></x-slot:meta>
        @if (in_array($take->status, ['counting', 'submitted']))
            <form method="POST" action="{{ route('stock-takes.cancel', $take) }}" data-confirm="{{ __('Cancel this stock take? Counts will be discarded.') }}">@csrf<button class="btn btn-soft-danger">{{ __('Cancel') }}</button></form>
        @endif
        @if ($take->status === 'counting')
            <form method="POST" action="{{ route('stock-takes.submit', $take) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-send"></i> {{ __('Submit for approval') }}</button></form>
        @endif
        @if (in_array($take->status, ['counting', 'submitted']))
            @can('stock.take.approve')
                <form method="POST" action="{{ route('stock-takes.post', $take) }}" data-confirm="{{ __('Post all counted variances to stock? This cannot be undone.') }}" data-confirm-button="{{ __('Post variances') }}">@csrf
                    <button class="btn btn-success"><i class="bi bi-check2-all"></i> {{ __('Approve & post') }}</button>
                </form>
            @endcan
        @endif
    </x-page-header>
    <livewire:inventory.stock-take-counter :take="$take" />
</x-layouts.app>
