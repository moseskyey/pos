@php $canCost = auth()->user()->can('products.view_cost'); @endphp
<x-layouts.app :title="$adjustment->number" :breadcrumbs="[__('Adjustments') => route('adjustments.index'), $adjustment->number]">
    <x-page-header :title="$adjustment->number">
        <x-slot:meta><div class="mt-2 d-flex gap-2 align-items-center"><x-status-badge :status="$adjustment->status" /><span class="text-body-secondary small">{{ $adjustment->reason->label() }} · {{ $adjustment->branch->name }}</span></div></x-slot:meta>
        @if ($adjustment->status === 'pending')
            @can('stock.adjust.approve')
                <button class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-lg"></i> {{ __('Reject') }}</button>
                <form method="POST" action="{{ route('adjustments.approve', $adjustment) }}" data-confirm="{{ __('Post this adjustment to stock?') }}" data-confirm-button="{{ __('Approve') }}">@csrf
                    <button class="btn btn-success"><i class="bi bi-check2"></i> {{ __('Approve & post') }}</button>
                </form>
            @endcan
        @endif
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('Items')" :flush="true">
                <div class="table-responsive">
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th class="text-end">{{ __('Quantity') }}</th>@if ($canCost)<th class="text-end">{{ __('Value') }}</th>@endif</tr></thead>
                        <tbody>
                        @foreach ($adjustment->items as $item)
                            <tr>
                                <td data-label="{{ __('Product') }}" class="fw-semibold">{{ $item->product->name }}</td>
                                <td data-label="{{ __('Batch') }}" class="small">{{ $item->batch_no ?: '—' }}{{ $item->expiry_date ? ' · '.format_date($item->expiry_date) : '' }}</td>
                                <td data-label="{{ __('Quantity') }}" class="text-end fw-semibold {{ $item->direction === 'in' ? 'text-success' : 'text-danger' }}">{{ $item->direction === 'in' ? '+' : '−' }}{{ qty($item->quantity) }} {{ $item->product->unit?->short_name }}</td>
                                @if ($canCost)<td data-label="{{ __('Value') }}" class="text-end text-money">{{ money(\App\Support\Money::mul($item->quantity, $item->unit_cost)) }}</td>@endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Details')">
                <dl class="info-list mb-0">
                    <dt>{{ __('Created') }}</dt><dd>{{ format_date($adjustment->created_at, true) }} · {{ $adjustment->creator?->name }}</dd>
                    @if ($adjustment->approved_at)
                        <dt>{{ $adjustment->status === 'rejected' ? __('Rejected') : __('Approved') }}</dt><dd>{{ format_date($adjustment->approved_at, true) }} · {{ $adjustment->approver?->name }}</dd>
                    @endif
                    @if ($adjustment->rejection_reason)<dt>{{ __('Rejection reason') }}</dt><dd class="text-danger">{{ $adjustment->rejection_reason }}</dd>@endif
                    <dt>{{ __('Note') }}</dt><dd class="mb-0">{{ $adjustment->note ?: '—' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>

    @push('modals')
        <x-modal id="rejectModal" :title="__('Reject adjustment')">
            <form method="POST" action="{{ route('adjustments.reject', $adjustment) }}" id="rejectForm">@csrf
                <x-textarea name="rejection_reason" :label="__('Reason')" required class="mb-0" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-danger" form="rejectForm">{{ __('Reject') }}</button>
            </x-slot:footer>
        </x-modal>
    @endpush
</x-layouts.app>
