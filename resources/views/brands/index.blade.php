<x-layouts.app :title="__('Brands')" :breadcrumbs="[__('Products') => route('products.index'), __('Brands')]">
    <x-page-header :title="__('Brands')" :subtitle="__('Manufacturers and product brands.')">
        <button type="button" class="btn btn-primary" onclick="Livewire.dispatch('open-create')"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </x-page-header>
    <livewire:tables.brands-table />
</x-layouts.app>
