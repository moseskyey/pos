<x-layouts.app :title="__('Customer payments')" :breadcrumbs="[__('Customers') => route('customers.index'), __('Payments')]">
    <x-page-header :title="__('Customer payments')" :subtitle="__('Debt repayments, allocated to the oldest invoices first.')">
        <a href="{{ route('customer-payments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Receive payment') }}</a>
    </x-page-header>
    <livewire:tables.customer-payments-table />
</x-layouts.app>
