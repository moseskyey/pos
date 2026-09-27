<x-layouts.admin :title="__('Payments')" :breadcrumbs="[__('Payments')]">
    <x-page-header :title="__('Subscription payments')" :subtitle="__('Mobile money pushes, manual payments and refunds across all businesses.')" />
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Received this month')" :value="money($thisMonth)" icon="bi-cash-stack" color="success" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Received this year')" :value="money($thisYear)" icon="bi-calendar3" color="primary" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Awaiting confirmation')" :value="number_format($pending)" icon="bi-hourglass-split" color="warning" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Failed (30 days)')" :value="number_format($failed)" icon="bi-x-octagon" color="danger" /></div>
    </div>
    <livewire:admin.payments-table />
    @push('modals')@include('admin.partials.refund-modal')@endpush
</x-layouts.admin>
