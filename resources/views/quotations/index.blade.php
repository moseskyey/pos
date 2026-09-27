<x-layouts.app :title="__('Quotations')" :breadcrumbs="[__('Sales'), __('Quotations')]">
    <x-page-header :title="__('Quotations')" :subtitle="__('Price quotes you can send as PDF and convert to a sale in one click.')">
        <a href="{{ route('quotations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New quotation') }}</a>
    </x-page-header>
    <livewire:tables.quotations-table />
</x-layouts.app>
