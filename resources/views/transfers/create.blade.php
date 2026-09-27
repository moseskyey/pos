<x-layouts.app :title="__('New transfer')" :breadcrumbs="[__('Transfers') => route('transfers.index'), __('New')]">
    <x-page-header :title="__('New stock transfer')" />
    <livewire:inventory.transfer-form />
</x-layouts.app>
