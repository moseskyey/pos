<x-layouts.app :title="$quotation ? __('Edit quotation') : __('New quotation')" :breadcrumbs="[__('Quotations') => route('quotations.index'), $quotation?->number ?? __('New')]">
    <x-page-header :title="$quotation ? __('Edit :n', ['n' => $quotation->number]) : __('New quotation')" />
    <livewire:sales.quotation-form :quotation="$quotation" />
</x-layouts.app>
