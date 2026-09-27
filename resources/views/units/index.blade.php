<x-layouts.app :title="__('Units')" :breadcrumbs="[__('Products') => route('products.index'), __('Units')]">
    <x-page-header :title="__('Units')" :subtitle="__('Units of measure and default conversions.')">
        <button type="button" class="btn btn-primary" onclick="Livewire.dispatch('open-create')"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </x-page-header>
    <livewire:tables.units-table />
</x-layouts.app>
