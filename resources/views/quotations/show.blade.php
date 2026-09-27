@php $expired = $quotation->valid_until && $quotation->valid_until->isPast() && ! $quotation->valid_until->isToday(); @endphp
<x-layouts.app :title="$quotation->number" :breadcrumbs="[__('Quotations') => route('quotations.index'), $quotation->number]">
    <x-page-header :title="$quotation->number">
        <x-slot:meta><div class="d-flex gap-2 align-items-center mt-2 small text-body-secondary">
            @if ($quotation->status->value === 'converted')
                <span class="badge rounded-pill text-bg-success-soft status-badge">{{ __('Converted') }}</span>
                <a href="{{ route('sales.show', $quotation->converted_sale_id) }}" class="font-monospace">{{ $quotation->convertedSale?->number }}</a>
            @else
                <span class="badge rounded-pill text-bg-{{ $expired ? 'danger' : 'info' }}-soft status-badge">{{ $expired ? __('Expired') : __('Open') }}</span>
            @endif
            <span>{{ __('Valid until :d', ['d' => format_date($quotation->valid_until)]) }}</span>
            <span>· {{ $quotation->customer?->name ?? __('Walk-in') }}</span>
        </div></x-slot:meta>
        <a href="{{ route('receipts.invoice', $quotation) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}</a>
        @if ($quotation->status->value === 'quotation')
            <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
            @can('pos.access')
                <form method="POST" action="{{ route('quotations.convert', $quotation) }}">@csrf
                    <button class="btn btn-success"><i class="bi bi-arrow-right-circle"></i> {{ __('Convert to sale') }}</button>
                </form>
            @endcan
        @endif
    </x-page-header>
    <div class="card"><div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
            <tbody>
            @foreach ($quotation->items as $item)
                <tr>
                    <td data-label="{{ __('Item') }}" class="fw-semibold">{{ $item->name }}</td>
                    <td data-label="{{ __('Qty') }}" class="text-end">{{ qty($item->quantity) }} {{ $item->unit_name }}</td>
                    <td data-label="{{ __('Price') }}" class="text-end text-money">{{ money($item->unit_price) }}</td>
                    <td data-label="{{ __('Discount') }}" class="text-end text-money">{{ $item->discount_amount > 0 ? money($item->discount_amount) : '—' }}</td>
                    <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end">{{ __('Discount') }}</td><td class="text-end text-money">{{ money($quotation->discount_total) }}</td></tr>
                <tr><td colspan="4" class="text-end">{{ __('VAT') }}</td><td class="text-end text-money">{{ money($quotation->tax_total) }}</td></tr>
                <tr><td colspan="4" class="text-end fs-6">{{ __('Total') }}</td><td class="text-end text-money fs-6">{{ money($quotation->total) }}</td></tr>
            </tfoot>
        </table>
    </div></div>
    @if ($quotation->note)<x-card :title="__('Notes / terms')" class="mt-4">{{ $quotation->note }}</x-card>@endif
</x-layouts.app>
