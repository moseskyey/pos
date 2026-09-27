<form wire:submit="save">
    @if (! $branch)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> {{ __('Select a single branch in the navbar to record an adjustment.') }}</div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('Products')" icon="bi-box-seam">
                <div class="mb-3">@include('livewire.partials.product-search')</div>
                @error('items')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                @if (! $items)
                    <x-empty-state icon="bi-upc-scan" :title="__('Scan or search products to adjust')" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-stack">
                            <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('On hand') }}</th><th style="width:110px">{{ __('In/Out') }}</th><th style="width:120px">{{ __('Quantity') }}</th>
                                @if ($canCost)<th style="width:120px">{{ __('Unit cost') }}</th>@endif<th></th></tr></thead>
                            <tbody>
                            @foreach ($items as $i => $item)
                                <tr wire:key="adj-{{ $i }}">
                                    <td data-label="{{ __('Product') }}">
                                        <div class="fw-semibold">{{ $item['name'] }}</div>
                                        @if ($item['batched'] && $item['direction'] === 'in')
                                            <div class="d-flex gap-1 mt-1">
                                                <input type="text" class="form-control form-control-sm" wire:model="items.{{ $i }}.batch_no" placeholder="{{ __('Batch no.') }}">
                                                <input type="date" class="form-control form-control-sm" wire:model="items.{{ $i }}.expiry_date" aria-label="{{ __('Expiry date') }}">
                                            </div>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('On hand') }}" class="text-end text-body-secondary">{{ $item['on_hand'] !== null ? qty($item['on_hand']) : '—' }} {{ $item['unit'] }}</td>
                                    <td data-label="{{ __('In/Out') }}">
                                        <select class="form-select form-select-sm" wire:model.live="items.{{ $i }}.direction">
                                            <option value="in">+ {{ __('In') }}</option><option value="out">− {{ __('Out') }}</option>
                                        </select>
                                    </td>
                                    <td data-label="{{ __('Quantity') }}">
                                        <input type="number" step="0.001" min="0" class="form-control form-control-sm @error('items.'.$i.'.quantity') is-invalid @enderror" wire:model="items.{{ $i }}.quantity">
                                    </td>
                                    @if ($canCost)
                                        <td data-label="{{ __('Unit cost') }}"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="items.{{ $i }}.unit_cost"></td>
                                    @endif
                                    <td class="text-end" data-label=""><button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeItem({{ $i }})" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Adjustment')" icon="bi-sliders">
                <x-select wire:model.live="reason" :label="__('Reason')" :options="$reasons" required />
                <x-textarea wire:model="note" :label="__('Note')" rows="3" />
                <div class="small text-body-secondary mb-0">
                    <i class="bi bi-info-circle"></i>
                    @can('stock.adjust.approve')
                        {{ __('You can approve adjustments, so this will be posted to stock immediately.') }}
                    @else
                        {{ __('A manager must approve this adjustment before stock changes.') }}
                    @endcan
                </div>
            </x-card>
        </div>
    </div>
    <div class="sticky-actions">
        <a href="{{ route('adjustments.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled" @disabled(! $branch)>
            <span wire:loading wire:target="save" class="spinner-border"></span> <i class="bi bi-check2"></i> {{ __('Save adjustment') }}
        </button>
    </div>
</form>
