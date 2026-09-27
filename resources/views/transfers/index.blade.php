<x-layouts.app :title="__('Stock transfers')" :breadcrumbs="[__('Inventory'), __('Transfers')]">
    <x-page-header :title="__('Stock transfers')" :subtitle="__('Move stock between branches.')">
        @can('stock.transfer')<a href="{{ route('transfers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New transfer') }}</a>@endcan
    </x-page-header>
    <livewire:tables.transfers-table />
</x-layouts.app>
