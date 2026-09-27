<x-layouts.app :title="__('Purchase orders')" :breadcrumbs="[__('Purchases'), __('Purchase orders')]">
    <x-page-header :title="__('Purchase orders')" :subtitle="__('Draft → sent → partially received → received.')">
        @can('purchases.manage')
            <a href="{{ route('reorder.index') }}" class="btn btn-outline-secondary"><i class="bi bi-lightning-charge"></i> {{ __('Reorder suggestions') }}</a>
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New purchase order') }}</a>
        @endcan
    </x-page-header>
    <livewire:tables.purchase-orders-table />
</x-layouts.app>
