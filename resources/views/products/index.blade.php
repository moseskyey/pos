<x-layouts.app :title="__('Products')" :breadcrumbs="[__('Products')]">
    <x-page-header :title="__('Products')" :subtitle="__('Your catalogue: prices, barcodes, units and variants.')">
        @can('products.import')
            <div class="btn-group">
                <a href="{{ route('products.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> {{ __('Import') }}</a>
                <a href="{{ route('products.export') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> {{ __('Export all') }}</a>
            </div>
        @endcan
        @can('products.edit_price')
            <a href="{{ route('products.bulk-price') }}" class="btn btn-outline-secondary"><i class="bi bi-percent"></i> {{ __('Bulk price update') }}</a>
        @endcan
        @can('products.create')
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add product') }}</a>
        @endcan
    </x-page-header>
    <livewire:tables.products-table />
</x-layouts.app>
