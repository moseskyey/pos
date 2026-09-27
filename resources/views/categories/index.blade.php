<x-layouts.app :title="__('Categories')" :breadcrumbs="[__('Products') => route('products.index'), __('Categories')]">
    <x-page-header :title="__('Categories')" :subtitle="__('Group your products (two levels).')">
        <button type="button" class="btn btn-primary" onclick="Livewire.dispatch('open-create')"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </x-page-header>
    <livewire:tables.categories-table />
</x-layouts.app>
