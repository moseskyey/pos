<x-layouts.app :title="__('Purchase returns')" :breadcrumbs="[__('Purchases'), __('Returns')]">
    <x-page-header :title="__('Returns to suppliers')" :subtitle="__('Stock goes out and the supplier balance is reduced.')">
        <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New return') }}</a>
    </x-page-header>
    <livewire:tables.purchase-returns-table />
</x-layouts.app>
