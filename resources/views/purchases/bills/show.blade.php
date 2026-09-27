<x-layouts.app :title="__('Bill')" :breadcrumbs="[__('Supplier bills') => route('supplier-bills.index'), $bill->bill_no ?? '#'.$bill->id]">
    <x-page-header :title="__('Bill :n', ['n' => $bill->bill_no ?: ($bill->goodsReceipt?->number ?? '#'.$bill->id)])">
        <x-slot:meta><div class="mt-2 small text-body-secondary d-flex gap-2 align-items-center">
            <x-status-badge :status="$bill->isOverdue() ? 'overdue' : $bill->status" />
            <a href="{{ route('suppliers.show', $bill->supplier) }}">{{ $bill->supplier->name }}</a>
            <span>· {{ format_date($bill->bill_date) }} · {{ __('due :d', ['d' => format_date($bill->due_date)]) }}</span>
        </div></x-slot:meta>
        @if ($bill->status !== 'paid')<a href="{{ route('supplier-payments.create', ['supplier' => $bill->supplier_id]) }}" class="btn btn-success"><i class="bi bi-cash"></i> {{ __('Pay') }}</a>@endif
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat-card :label="__('Total')" :value="money($bill->total)" icon="bi-journal-text" :hint="__('VAT :v', ['v' => money($bill->tax_total)])" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Paid')" :value="money($bill->paid)" icon="bi-cash" color="success" /></div>
        <div class="col-md-4"><x-stat-card :label="__('Balance')" :value="money($bill->balance())" icon="bi-hourglass" color="danger" /></div>
    </div>
    @if ($bill->goodsReceipt)<p><a href="{{ route('goods-receipts.show', $bill->goodsReceipt) }}"><i class="bi bi-box-arrow-in-down"></i> {{ $bill->goodsReceipt->number }}</a></p>@endif
    @if ($bill->description)<x-card :title="__('Description')" class="mb-4">{{ $bill->description }}</x-card>@endif
    <x-card :title="__('Payments applied')" :flush="true">
        <ul class="list-group list-group-flush">
            @forelse ($bill->allocations as $a)
                <li class="list-group-item d-flex justify-content-between"><span class="font-monospace">{{ $a->payment?->number }} <span class="small text-body-secondary">· {{ format_date($a->payment?->paid_at) }}</span></span><span class="fw-semibold text-money">{{ money($a->amount) }}</span></li>
            @empty
                <li class="list-group-item text-body-secondary small">{{ __('No payments yet') }}</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.app>
