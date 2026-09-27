<x-layouts.app :title="$return->number" :breadcrumbs="[__('Purchase returns') => route('purchase-returns.index'), $return->number]">
    <x-page-header :title="$return->number" :subtitle="$return->supplier->name.' · '.format_date($return->created_at).' · '.$return->reason" />
    <div class="card"><div class="table-responsive"><table class="table table-stack">
        <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>
        @foreach ($return->items as $item)
            <tr><td data-label="{{ __('Product') }}" class="fw-semibold">{{ $item->product->name }}</td><td data-label="{{ __('Qty') }}" class="text-end">{{ qty($item->quantity) }}</td>
                <td data-label="{{ __('Unit cost') }}" class="text-end text-money">{{ money($item->unit_cost) }}</td><td data-label="{{ __('Total') }}" class="text-end text-money">{{ money($item->line_total) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3" class="text-end">{{ __('Total incl. VAT') }}</td><td class="text-end text-money">{{ money($return->total) }}</td></tr></tfoot>
    </table></div></div>
</x-layouts.app>
