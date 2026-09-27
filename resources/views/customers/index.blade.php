<x-layouts.app :title="__('Customers')" :breadcrumbs="[__('Customers')]">
    <x-page-header :title="__('Customers')" :subtitle="__('Accounts, credit (deni), store credit and loyalty.')">
        @can('customers.payments')<a href="{{ route('customer-payments.create') }}" class="btn btn-outline-secondary"><i class="bi bi-cash"></i> {{ __('Receive payment') }}</a>@endcan
        @can('customers.manage')<a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> {{ __('Add customer') }}</a>@endcan
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Customers')" :value="number_format($count)" icon="bi-people" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Debtors')" :value="number_format($debtors)" icon="bi-person-exclamation" color="warning" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total owed (deni)')" :value="money($owed)" icon="bi-journal-text" color="danger" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Store credit held')" :value="money($storeCredit)" icon="bi-wallet2" color="info" /></div>
    </div>
    <livewire:tables.customers-table />
</x-layouts.app>
