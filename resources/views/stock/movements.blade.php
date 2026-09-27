<x-layouts.app :title="__('Stock movements')" :breadcrumbs="[__('Inventory'), __('Movements')]">
    <x-page-header :title="__('Stock movements')" :subtitle="__('Read-only ledger of every quantity change.')" />
    <livewire:tables.stock-movements-table />
</x-layouts.app>
