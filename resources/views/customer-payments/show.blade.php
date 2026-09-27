<x-layouts.app :title="$payment->number" :breadcrumbs="[__('Payments') => route('customer-payments.index'), $payment->number]">
    <x-page-header :title="$payment->number" :subtitle="format_date($payment->created_at, true).' · '.$payment->user?->name" />
    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card :label="__('Amount')" :value="money($payment->amount)" icon="bi-cash" color="success" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Customer')" :value="$payment->customer->name" icon="bi-person" :href="route('customers.show', $payment->customer)" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Method')" :value="$payment->method->label()" icon="bi-credit-card" color="info" :hint="$payment->reference" /></div>
    </div>
    <x-card :title="__('Allocated to invoices')" :flush="true">
        <ul class="list-group list-group-flush">
            @forelse ($payment->allocations as $a)
                <li class="list-group-item d-flex justify-content-between"><a href="{{ route('sales.show', $a->sale_id) }}" class="font-monospace text-decoration-none">{{ $a->sale?->number }}</a><span class="fw-semibold text-money">{{ money($a->amount) }}</span></li>
            @empty
                <li class="list-group-item text-body-secondary">{{ __('Applied to opening balance / account.') }}</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.app>
