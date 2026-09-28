<x-layouts.app :title="__('Cheques')" :breadcrumbs="[__('Customers'), __('Cheques')]">
    <x-page-header :title="__('Cheques')" :subtitle="__('Track cheques until they clear. A bounced cheque reverses its payment.')" />
    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card :label="__('To receive (pending)')" :value="money($receivable)" icon="bi-box-arrow-in-down" color="success" /></div>
        <div class="col-md-4"><x-stat-card :label="__('To pay out (pending)')" :value="money($payable)" icon="bi-box-arrow-up" color="warning" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Due for banking now')" :value="number_format($dueNow)" icon="bi-calendar-check" :color="$dueNow ? 'danger' : 'secondary'" /></div>
    </div>
    <livewire:tables.cheques-table />
</x-layouts.app>
