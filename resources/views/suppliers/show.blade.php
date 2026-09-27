<x-layouts.app :title="$supplier->name" :breadcrumbs="[__('Suppliers') => route('suppliers.index'), $supplier->name]">
    <x-page-header :title="$supplier->name">
        <x-slot:meta><div class="small text-body-secondary mt-2 d-flex flex-wrap gap-3">
            @if ($supplier->contact_person)<span><i class="bi bi-person"></i> {{ $supplier->contact_person }}</span>@endif
            @if ($supplier->phone)<span><i class="bi bi-phone"></i> {{ $supplier->displayPhone() }}</span>@endif
            @if ($supplier->tin)<span>TIN {{ $supplier->tin }}</span>@endif
            <span>{{ trans_choice(':count day terms|:count days terms', $supplier->payment_terms_days) }}</span>
        </div></x-slot:meta>
        @can('purchases.manage')<a href="{{ route('purchase-orders.create', ['supplier' => $supplier->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-cart-plus"></i> {{ __('New PO') }}</a>@endcan
        @can('purchases.receive')<a href="{{ route('goods-receipts.create', ['supplier' => $supplier->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive goods') }}</a>@endcan
        @if ($supplier->balance > 0)@can('supplier.payments')<a href="{{ route('supplier-payments.create', ['supplier' => $supplier->id]) }}" class="btn btn-success"><i class="bi bi-cash"></i> {{ __('Pay') }}</a>@endcan @endif
        @can('suppliers.manage')<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary"><i class="bi bi-pencil"></i></a>@endcan
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('We owe')" :value="money($supplier->balance)" icon="bi-journal-text" color="danger" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total purchased')" :value="money($purchased)" icon="bi-cart-check" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Overdue (61+ days)')" :value="money(\App\Support\Money::add($aging['61_90'], $aging['over_90']))" icon="bi-exclamation-triangle" color="warning" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Current (0–30)')" :value="money($aging['current'])" icon="bi-hourglass" color="info" /></div>
    </div>
    <ul class="nav nav-tabs-modern mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-bills" type="button">{{ __('Bills') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-payments" type="button">{{ __('Payments') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-orders" type="button">{{ __('Purchase orders') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-grn" type="button">{{ __('Goods received') }}</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-ledger" type="button">{{ __('Statement') }}</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="t-bills"><livewire:tables.supplier-bills-table :supplier-id="$supplier->id" /></div>
        <div class="tab-pane fade" id="t-payments"><livewire:tables.supplier-payments-table :supplier-id="$supplier->id" /></div>
        <div class="tab-pane fade" id="t-orders"><livewire:tables.purchase-orders-table :supplier-id="$supplier->id" /></div>
        <div class="tab-pane fade" id="t-grn"><livewire:tables.goods-receipts-table :supplier-id="$supplier->id" /></div>
        <div class="tab-pane fade" id="t-ledger">
            <div class="card"><div class="table-responsive"><table class="table table-sm table-stack">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Paid / returned') }}</th><th class="text-end">{{ __('Billed') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr><td data-label="{{ __('Date') }}">{{ format_date($e->created_at) }}</td><td data-label="{{ __('Description') }}">{{ $e->note }}</td>
                        <td data-label="{{ __('Paid / returned') }}" class="text-end text-money text-success">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                        <td data-label="{{ __('Billed') }}" class="text-end text-money">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                        <td data-label="{{ __('Balance') }}" class="text-end text-money fw-semibold">{{ money($e->balance_after) }}</td></tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-journal" :title="__('No transactions')" /></td></tr>
                @endforelse
                </tbody>
            </table></div></div>
        </div>
    </div>
</x-layouts.app>
