<x-layouts.app :title="__('New return')" :breadcrumbs="[__('Returns') => route('returns.index'), __('New')]">
    <x-page-header :title="__('Process a return')" :subtitle="__('Pick the items and quantities the customer is bringing back.')" />
    <livewire:sales.return-form :sale="$saleId" />
</x-layouts.app>
