<x-layouts.app :title="__('Receive goods')" :breadcrumbs="[__('Goods received') => route('goods-receipts.index'), __('New')]">
    <x-page-header :title="__('Receive goods (GRN)')" :subtitle="current_branch()?->name.' · '.__('Unit costs exclude VAT.')" />
    <livewire:purchases.document-form mode="receipt" :order="$order" :supplier="$supplier" />
</x-layouts.app>
