<x-layouts.app :title="$order->number" :breadcrumbs="[__('Purchase orders') => route('purchase-orders.index'), $order->number]">
    <x-page-header :title="$order->number">
        <x-slot:meta><div class="mt-2 d-flex flex-wrap gap-2 align-items-center small text-body-secondary">
            <x-status-badge :status="$order->status" />
            <span><i class="bi bi-building"></i> <a href="{{ route('suppliers.show', $order->supplier) }}">{{ $order->supplier->name }}</a></span>
            <span>{{ __('Ordered :d', ['d' => format_date($order->order_date)]) }}</span>
            @if ($order->expected_date)<span>· {{ __('Expected :d', ['d' => format_date($order->expected_date)]) }}</span>@endif
            <span>· {{ $order->creator?->name }}</span>
        </div></x-slot:meta>
        <a href="{{ route('purchase-orders.pdf', $order) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        @can('purchases.manage')
            @if ($order->status === 'draft')
                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                <form method="POST" action="{{ route('purchase-orders.send', $order) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-send"></i> {{ __('Mark as sent') }}</button></form>
            @endif
            @if (in_array($order->status, ['draft', 'sent']))
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#emailPoModal"><i class="bi bi-envelope"></i> {{ __('Email to supplier') }}</button>
                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" data-confirm="{{ __('Cancel this purchase order?') }}">@csrf<button class="btn btn-soft-danger">{{ __('Cancel') }}</button></form>
            @endif
        @endcan
        @if ($order->isReceivable())
            @can('purchases.receive')<a href="{{ route('goods-receipts.create', ['order' => $order->id]) }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive goods') }}</a>@endcan
        @endif
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="table-responsive"><table class="table table-stack align-middle">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Ordered') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td data-label="{{ __('Product') }}" class="fw-semibold">{{ $item->product->name }}</td>
                        <td data-label="{{ __('Ordered') }}" class="text-end">{{ qty($item->quantity) }} {{ $item->product->unit?->short_name }}</td>
                        <td data-label="{{ __('Received') }}" class="text-end {{ $item->received_quantity >= $item->quantity ? 'text-success fw-semibold' : '' }}">{{ qty($item->received_quantity) }}</td>
                        <td data-label="{{ __('Unit cost') }}" class="text-end text-money">{{ money($item->unit_cost) }}</td>
                        <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($item->line_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end">{{ __('Subtotal') }}</td><td class="text-end text-money">{{ money($order->subtotal) }}</td></tr>
                    <tr><td colspan="4" class="text-end">{{ __('VAT') }}</td><td class="text-end text-money">{{ money($order->tax_total) }}</td></tr>
                    <tr><td colspan="4" class="text-end">{{ __('Total') }}</td><td class="text-end text-money">{{ money($order->total) }}</td></tr>
                </tfoot>
            </table></div></div>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Goods received')" :flush="true">
                <ul class="list-group list-group-flush">
                    @forelse ($order->receipts as $r)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('goods-receipts.show', $r) }}" class="font-monospace text-decoration-none">{{ $r->number }}</a><span class="small text-body-secondary">{{ format_date($r->received_at) }} · {{ money($r->total) }}</span></li>
                    @empty
                        <li class="list-group-item small text-body-secondary">{{ __('Nothing received yet') }}</li>
                    @endforelse
                </ul>
            </x-card>
            @if ($order->note)<x-card :title="__('Note')" class="mt-4">{{ $order->note }}</x-card>@endif
        </div>
    </div>

    @can('purchases.manage')
        @if (in_array($order->status, ['draft', 'sent']))
            @push('modals')
                <x-modal id="emailPoModal" :title="__('Email to supplier')">
                    <form method="POST" action="{{ route('purchase-orders.email', $order) }}" id="emailPoForm">@csrf
                        <x-input name="email" type="email" :label="__('Supplier email')" :value="old('email', $order->supplier->email)" required />
                        <x-textarea name="message" :label="__('Message (optional)')" rows="3" class="mb-0" />
                        <p class="small text-body-secondary mt-2 mb-0"><i class="bi bi-paperclip"></i> {{ __('The purchase order PDF is attached. A draft order is marked as sent.') }}</p>
                    </form>
                    <x-slot:footer>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" form="emailPoForm" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Send email') }}</button>
                    </x-slot:footer>
                </x-modal>
            @endpush
        @endif
    @endcan
</x-layouts.app>
