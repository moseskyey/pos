@php
    $cancel = match ($mode) { 'receipt' => route('goods-receipts.index'), 'return' => route('purchase-returns.index'), default => route('purchase-orders.index') };
    $label = match ($mode) { 'receipt' => __('Receive goods'), 'return' => __('Record return'), default => __('Save purchase order') };
@endphp
<form wire:submit="save">
    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('Products')" icon="bi-box-seam">
                @unless ($receiptId)
                    <div class="mb-3">@include('livewire.partials.product-search')</div>
                @endunless
                @error('items')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                @if (! $items)
                    <x-empty-state icon="bi-cart-plus" :title="__('Add products')" :message="$mode === 'order' ? __('Tip: use Reorder suggestions to build orders automatically.') : null" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-stack">
                            <thead><tr>
                                <th>{{ __('Product') }}</th>
                                @if ($mode === 'receipt' && $orderId)<th class="text-end">{{ __('Ordered / received') }}</th>@endif
                                @if ($mode === 'return' && $receiptId)<th class="text-end">{{ __('Returnable') }}</th>@endif
                                <th style="width:110px">{{ __('Qty') }}</th>
                                <th style="width:130px">{{ __('Unit cost') }}</th>
                                <th class="text-end">{{ __('Total') }}</th><th></th>
                            </tr></thead>
                            <tbody>
                            @foreach ($items as $i => $item)
                                <tr wire:key="doc-{{ $i }}">
                                    <td data-label="{{ __('Product') }}">
                                        <div class="fw-semibold">{{ $item['name'] }}</div>
                                        <div class="small text-body-secondary">{{ $item['sku'] }} · {{ $item['unit'] }} @if ($item['tax_rate'] > 0)<span class="badge text-bg-secondary-soft">VAT {{ $item['tax_rate'] }}%</span>@endif</div>
                                        @if ($mode === 'receipt' && ! empty($item['serialized']))
                                            <textarea class="form-control form-control-sm font-monospace mt-1" rows="2" wire:model.blur="items.{{ $i }}.serials"
                                                      placeholder="{{ __('Serial / IMEI numbers, one per line (optional)') }}" aria-label="{{ __('Serial numbers for :p', ['p' => $item['name']]) }}"></textarea>
                                            @php $serialCount = count(\App\Services\SerialService::parse($item['serials'] ?? '')); @endphp
                                            @if ($serialCount)<div class="small {{ $serialCount === (int) $item['quantity'] ? 'text-success' : 'text-danger' }}">{{ __(':n of :q serials', ['n' => $serialCount, 'q' => qty($item['quantity'])]) }}</div>@endif
                                        @endif
                                        @if ($mode === 'receipt' && $item['batched'])
                                            <div class="d-flex gap-1 mt-1">
                                                <input type="text" class="form-control form-control-sm" wire:model="items.{{ $i }}.batch_no" placeholder="{{ __('Batch no.') }}">
                                                <input type="date" class="form-control form-control-sm @error('items.'.$i.'.expiry_date') is-invalid @enderror" wire:model="items.{{ $i }}.expiry_date" aria-label="{{ __('Expiry') }}">
                                            </div>
                                        @endif
                                    </td>
                                    @if ($mode === 'receipt' && $orderId)<td data-label="{{ __('Ordered / received') }}" class="text-end small">{{ qty($item['ordered'] ?? 0) }} / {{ qty($item['received'] ?? 0) }}</td>@endif
                                    @if ($mode === 'return' && $receiptId)<td data-label="{{ __('Returnable') }}" class="text-end small">{{ qty($item['returnable'] ?? 0) }}</td>@endif
                                    <td data-label="{{ __('Qty') }}"><input type="number" step="0.001" min="0" class="form-control form-control-sm @error('items.'.$i.'.quantity') is-invalid @enderror" wire:model.live.debounce.400ms="items.{{ $i }}.quantity"></td>
                                    <td data-label="{{ __('Unit cost') }}"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live.debounce.400ms="items.{{ $i }}.unit_cost" @disabled($mode === 'return')></td>
                                    <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money((float) $item['quantity'] * (float) $item['unit_cost']) }}</td>
                                    <td class="text-end" data-label="">@unless ($locked)<button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeItem({{ $i }})"><i class="bi bi-trash"></i></button>@endunless</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Details')">
                <x-select wire:model="supplierId" :label="__('Supplier')" :options="$suppliers" :placeholder="__('Select supplier')" required :disabled="$locked" />
                @if ($mode === 'order')
                    <x-input wire:model="expectedDate" type="date" :label="__('Expected delivery')" />
                @elseif ($mode === 'receipt')
                    <x-input wire:model="date" type="date" :label="__('Received on')" required />
                    <x-input wire:model="invoiceNo" :label="__('Supplier invoice / delivery no.')" />
                @else
                    <x-input wire:model="reason" :label="__('Reason')" required :placeholder="__('e.g. Expired, damaged on delivery')" />
                @endif
                <x-textarea wire:model="note" :label="__('Note')" rows="2" class="mb-0" />
            </x-card>
            <div class="card mt-4 bg-primary-soft border-0"><div class="card-body">
                <div class="d-flex justify-content-between small"><span>{{ __('Subtotal (excl. VAT)') }}</span><span class="text-money">{{ money($subtotal) }}</span></div>
                <div class="d-flex justify-content-between small text-body-secondary"><span>{{ __('Input VAT') }}</span><span class="text-money">{{ money($tax) }}</span></div>
                <div class="d-flex justify-content-between fs-4 fw-bold mt-2"><span>{{ __('Total') }}</span><span class="text-money">{{ money($total) }}</span></div>
                @if ($mode === 'receipt')<div class="small text-body-secondary mt-2"><i class="bi bi-info-circle"></i> {{ setting('inventory.costing') === 'last' ? __('Cost prices are set to the last purchase cost.') : __('Cost prices update using moving average cost.') }}</div>@endif
            </div></div>
        </div>
    </div>
    <div class="sticky-actions">
        <a href="{{ $cancel }}" class="btn btn-light">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled"><span wire:loading wire:target="save" class="spinner-border"></span> <i class="bi bi-check2"></i> {{ $label }}</button>
    </div>
</form>
