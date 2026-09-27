<x-layouts.app :title="__('Batches & expiry')" :breadcrumbs="[__('Inventory'), __('Batches & expiry')]">
    <x-page-header :title="__('Batches & expiry')" :subtitle="__('Batch-tracked stock is sold first-expiry-first-out (FEFO).')" />
    <livewire:tables.batches-table />
</x-layouts.app>
