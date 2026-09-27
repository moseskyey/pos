<x-layouts.app :title="__('Stock levels')" :breadcrumbs="[__('Inventory'), __('Stock levels')]">
    <x-page-header :title="__('Stock levels')" :subtitle="current_branch() ? __('Branch: :b', ['b' => current_branch()->name]) : __('All branches')">
        @can('stock.adjust')<a href="{{ route('adjustments.create') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders"></i> {{ __('Adjust stock') }}</a>@endcan
        @can('stock.transfer')<a href="{{ route('transfers.create') }}" class="btn btn-primary"><i class="bi bi-truck"></i> {{ __('New transfer') }}</a>@endcan
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl"><x-stat-card :label="__('Products in stock')" :value="number_format($skus)" icon="bi-box-seam" /></div>
        @can('stock.value.view')
            @can('products.view_cost')<div class="col-6 col-xl"><x-stat-card :label="__('Value at cost')" :value="money($costValue)" icon="bi-cash-stack" color="info" /></div>@endcan
            <div class="col-6 col-xl"><x-stat-card :label="__('Value at retail')" :value="money($retailValue)" icon="bi-tags" color="success" /></div>
        @endcan
        <div class="col-6 col-xl"><x-stat-card :label="__('Low stock')" :value="number_format($low)" icon="bi-exclamation-triangle" color="warning" /></div>
        <div class="col-6 col-xl"><x-stat-card :label="__('Out of stock')" :value="number_format($out)" icon="bi-x-octagon" color="danger" /></div>
        <div class="col-6 col-xl"><x-stat-card :label="__('Expiring soon')" :value="number_format($expiring)" icon="bi-calendar2-x" color="secondary" :href="route('batches.index')" /></div>
    </div>
    <livewire:tables.stock-levels-table />
</x-layouts.app>
