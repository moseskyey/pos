<x-layouts.app :title="$order ? __('Edit purchase order') : __('New purchase order')" :breadcrumbs="[__('Purchase orders') => route('purchase-orders.index'), $order?->number ?? __('New')]">
    <x-page-header :title="$order ? __('Edit :n', ['n' => $order->number]) : __('New purchase order')" :subtitle="__('Unit costs exclude VAT.')" />
    <livewire:purchases.document-form mode="order" :order="$order?->id" :supplier="$supplier" />
</x-layouts.app>
