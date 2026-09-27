<x-layouts.app :title="__('Suppliers')" :breadcrumbs="[__('Purchases'), __('Suppliers')]">
    <x-page-header :title="__('Suppliers')" :subtitle="__(':count suppliers · we owe :owed', ['count' => number_format($count), 'owed' => money($owed)])">
        @can('supplier.payments')<a href="{{ route('supplier-payments.create') }}" class="btn btn-outline-secondary"><i class="bi bi-cash"></i> {{ __('Pay supplier') }}</a>@endcan
        @can('suppliers.manage')<a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add supplier') }}</a>@endcan
    </x-page-header>
    <livewire:tables.suppliers-table />
</x-layouts.app>
