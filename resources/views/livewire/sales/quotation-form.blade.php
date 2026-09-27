<form wire:submit="save">
    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('Items')" icon="bi-box-seam">
                <div class="mb-3">@include('livewire.partials.product-search')</div>
                @error('lines')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                @if (! $lines)
                    <x-empty-state icon="bi-file-earmark-text" :title="__('Add products to the quotation')" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-stack">
                            <thead><tr><th>{{ __('Item') }}</th><th style="width:100px">{{ __('Qty') }}</th><th style="width:130px">{{ __('Unit price') }}</th><th style="width:170px">{{ __('Discount') }}</th><th class="text-end">{{ __('Total') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($lines as $i => $line)
                                <tr wire:key="ql-{{ $i }}">
                                    <td data-label="{{ __('Item') }}" class="fw-semibold">{{ $line['name'] }} <span class="small text-body-secondary">{{ $line['unit'] }}</span></td>
                                    <td data-label="{{ __('Qty') }}"><input type="number" step="0.001" min="0" class="form-control form-control-sm" wire:model.live.debounce.400ms="lines.{{ $i }}.qty"></td>
                                    <td data-label="{{ __('Unit price') }}"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live.debounce.400ms="lines.{{ $i }}.unit_price"></td>
                                    <td data-label="{{ __('Discount') }}">
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" class="form-control" wire:model.live.debounce.400ms="lines.{{ $i }}.discount_value">
                                            <select class="form-select" style="max-width:70px" wire:model.live="lines.{{ $i }}.discount_type"><option value="percent">%</option><option value="fixed">TSh</option></select>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Total') }}" class="text-end text-money fw-semibold">{{ money($totals['lines'][$i]['line_total'] ?? 0) }}</td>
                                    <td class="text-end" data-label=""><button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeLine({{ $i }})"><i class="bi bi-trash"></i></button></td>
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
                <x-select wire:model.live="customerId" :label="__('Customer')" :options="$customers" :placeholder="__('Walk-in / prospect')" />
                <x-input wire:model="validUntil" type="date" :label="__('Valid until')" required />
                <div class="row g-2">
                    <div class="col-7"><x-input wire:model.live.debounce.400ms="cartDiscountValue" type="number" step="0.01" min="0" :label="__('Overall discount')" /></div>
                    <div class="col-5"><x-select wire:model.live="cartDiscountType" :label="__('Type')" :options="['percent' => '%', 'fixed' => 'TSh']" /></div>
                </div>
                <x-textarea wire:model="note" :label="__('Notes / terms')" rows="3" class="mb-0" />
            </x-card>
            <div class="card mt-4 bg-primary-soft border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between small"><span>{{ __('Subtotal') }}</span><span class="text-money">{{ money($totals['subtotal']) }}</span></div>
                    <div class="d-flex justify-content-between small text-success"><span>{{ __('Discount') }}</span><span class="text-money">−{{ money($totals['discount_total']) }}</span></div>
                    <div class="d-flex justify-content-between small text-body-secondary"><span>{{ __('VAT') }}</span><span class="text-money">{{ money($totals['tax_total']) }}</span></div>
                    <div class="d-flex justify-content-between fs-4 fw-bold mt-2"><span>{{ __('Total') }}</span><span class="text-money">{{ money($totals['total']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="sticky-actions">
        <a href="{{ route('quotations.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled"><span wire:loading wire:target="save" class="spinner-border"></span> <i class="bi bi-check2"></i> {{ __('Save quotation') }}</button>
    </div>
    <x-manager-pin-modal :approval="$approval" />
</form>
