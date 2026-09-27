<x-layouts.app :title="__('Return to supplier')" :breadcrumbs="[__('Purchase returns') => route('purchase-returns.index'), __('New')]">
    <x-page-header :title="__('Return goods to supplier')" />
    <livewire:purchases.document-form mode="return" :receipt="$receipt" />
</x-layouts.app>
