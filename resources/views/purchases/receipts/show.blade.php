<x-layouts.app :title="$receipt->number" :breadcrumbs="[__('Goods received') => route('goods-receipts.index'), $receipt->number]">
    <x-page-header :title="$receipt->number">
        <x-slot:meta><div class="mt-2 small text-body-secondary d-flex flex-wrap gap-3">
            <span><i class="bi bi-building"></i> <a href="{{ route('suppliers.show', $receipt->supplier) }}">{{ $receipt->supplier->name }}</a></span>
            <span>{{ __('Received :d', ['d' => format_date($receipt->received_at)]) }}</span>
            @if ($receipt->supplier_invoice_no)<span>{{ __('Invoice') }} {{ $receipt->supplier_invoice_no }}</span>@endif
            @if ($receipt->purchaseOrder)<span>PO <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}">{{ $receipt->purchaseOrder->number }}</a></span>@endif
            <span>{{ $receipt->user?->name }}</span>
        </div></x-slot:meta>
        @can('purchases.return')<a href="{{ route('purchase-returns.create', ['receipt' => $receipt->id]) }}" class="btn btn-outline-danger"><i class="bi bi-box-arrow-up"></i> {{ __('Return to supplier') }}</a>@endcan
        @if ($receipt->bill)<a href="{{ route('supplier-bills.show', $receipt->bill) }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text"></i> {{ __('Bill') }} <x-status-badge :status="$receipt->bill->status" /></a>@endif
    </x-page-header>
    <div class="card"><div class="table-responsive"><table class="table table-stack">
        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch / expiry') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>
        @foreach ($receipt->items as $item)
            <tr>
                <td data-label="{{ __('Product') }}" class="fw-semibold">{{ $item->product->name }} @if ($item->returned_quantity > 0)<span class="badge text-bg-warning-soft">{{ __(':q returned', ['q' => qty($item->returned_quantity)]) }}</span>@endif</td>
                <td data-label="{{ __('Batch / expiry') }}" class="small">{{ $item->batch_no ?: '—' }}{{ $item->expiry_date ? ' · '.format_date($item->expiry_date) : '' }}</td>
                <td data-label="{{ __('Qty') }}" class="text-end">{{ qty($item->quantity) }} {{ $item->product->unit?->short_name }}</td>
                <td data-label="{{ __('Unit cost') }}" class="text-end text-money">{{ money($item->unit_cost) }}</td>
                <td data-label="{{ __('VAT') }}" class="text-end text-money">{{ money($item->tax_amount) }}</td>
                <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="text-end">{{ __('Subtotal') }}</td><td class="text-end text-money">{{ money($receipt->subtotal) }}</td></tr>
            <tr><td colspan="5" class="text-end">{{ __('Input VAT') }}</td><td class="text-end text-money">{{ money($receipt->tax_total) }}</td></tr>
            <tr><td colspan="5" class="text-end">{{ __('Total') }}</td><td class="text-end text-money">{{ money($receipt->total) }}</td></tr>
        </tfoot>
    </table></div></div>
</x-layouts.app>
