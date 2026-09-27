<x-layouts.app :title="__('Stock adjustments')" :breadcrumbs="[__('Inventory'), __('Adjustments')]">
    <x-page-header :title="__('Stock adjustments')" :subtitle="__('Damaged, expired, stolen or found stock and opening balances.')">
        @can('stock.adjust')<a href="{{ route('adjustments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New adjustment') }}</a>@endcan
    </x-page-header>
    <livewire:tables.adjustments-table />
</x-layouts.app>
