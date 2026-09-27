<x-layouts.app :title="__('Goods received')" :breadcrumbs="[__('Purchases'), __('Goods received')]">
    <x-page-header :title="__('Goods received notes')" :subtitle="__('Stock in from suppliers. Each GRN creates a supplier bill.')">
        @can('purchases.receive')<a href="{{ route('goods-receipts.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive goods') }}</a>@endcan
    </x-page-header>
    <livewire:tables.goods-receipts-table />
</x-layouts.app>
