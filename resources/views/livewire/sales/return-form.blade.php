<div>
    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('1. Find the sale')" icon="bi-search">
                <form wire:submit="find" class="d-flex gap-2">
                    <input type="text" class="form-control font-monospace @error('lookup') is-invalid @enderror" wire:model="lookup" placeholder="INV-DSM01-000123" autofocus aria-label="{{ __('Receipt number') }}">
                    <button class="btn btn-primary text-nowrap"><i class="bi bi-search"></i> {{ __('Find') }}</button>
                </form>
                @error('lookup')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if ($sale)
                    <div class="small text-body-secondary mt-2">{{ format_date($sale->created_at, true) }} · {{ $sale->customer?->name ?? __('Walk-in') }} · {{ __('Total') }} {{ money($sale->total) }}</div>
                @endif
            </x-card>

            @if ($sale)
                <x-card :title="__('2. Items to return')" icon="bi-box-seam" class="mt-4" :flush="true">
                    <x-slot:actions><button type="button" class="btn btn-sm btn-soft-primary" wire:click="returnAll">{{ __('Return all') }}</button></x-slot:actions>
                    <div class="table-responsive">
                        <table class="table align-middle table-stack mb-0">
                            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Returnable') }}</th><th class="text-end">{{ __('Refund / unit') }}</th><th style="width:120px">{{ __('Qty') }}</th><th style="width:140px">{{ __('Condition') }}</th></tr></thead>
                            <tbody>
                            @foreach ($lines as $id => $line)
                                <tr wire:key="rl-{{ $id }}" class="{{ $line['returnable'] <= 0 ? 'opacity-50' : '' }}">
                                    <td data-label="{{ __('Item') }}">
                                        <div class="fw-semibold">{{ $line['name'] }}</div>
                                        @if (! empty($line['serial_options']))
                                            <div class="small text-body-secondary mt-1">{{ __('Serials being returned:') }}</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach ($line['serial_options'] as $serial)
                                                    <div class="form-check form-check-inline me-0">
                                                        <input class="form-check-input" type="checkbox" id="rs-{{ $id }}-{{ $loop->index }}" value="{{ $serial }}" wire:model.live="lines.{{ $id }}.serials">
                                                        <label class="form-check-label small font-monospace" for="rs-{{ $id }}-{{ $loop->index }}">{{ $serial }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('Returnable') }}" class="text-end">{{ qty($line['returnable']) }} / {{ qty($line['sold']) }} {{ $line['unit'] }}</td>
                                    <td data-label="{{ __('Refund / unit') }}" class="text-end text-money">{{ money($line['unit_refund']) }}</td>
                                    <td data-label="{{ __('Qty') }}"><input type="number" step="0.001" min="0" max="{{ $line['returnable'] }}" class="form-control form-control-sm" wire:model.live.debounce.300ms="lines.{{ $id }}.quantity" @disabled($line['returnable'] <= 0)></td>
                                    <td data-label="{{ __('Condition') }}">
                                        <select class="form-select form-select-sm" wire:model="lines.{{ $id }}.condition" @disabled($line['returnable'] <= 0)>
                                            <option value="restock">{{ __('Restock') }}</option><option value="damaged">{{ __('Damaged') }}</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>
        <div class="col-lg-4">
            <x-card :title="__('3. Refund')" icon="bi-cash-coin">
                @if ($sale)
                    <div class="text-center mb-3">
                        <div class="small text-body-secondary">{{ __('Refund amount') }}</div>
                        <div class="fs-2 fw-bold text-money text-danger">{{ money($total) }}</div>
                    </div>
                    <x-textarea wire:model="reason" :label="__('Reason')" rows="2" required :placeholder="__('e.g. Expired, wrong size, customer changed mind')" />
                    <x-select wire:model.live="refundMethod" :label="__('Refund to')" :options="$methods" />
                    @if (in_array($refundMethod, ['mpesa', 'tigopesa', 'airtel', 'halopesa', 'bank']))
                        <x-input wire:model="reference" :label="__('Transaction reference')" required />
                    @endif
                    <button type="button" class="btn btn-danger w-100" wire:click="save" wire:loading.attr="disabled" @disabled($total <= 0)>
                        <span wire:loading wire:target="save" class="spinner-border"></span> <i class="bi bi-arrow-counterclockwise"></i> {{ __('Process return') }}
                    </button>
                    @cannot('sales.return')<div class="small text-body-secondary mt-2 text-center"><i class="bi bi-shield-lock"></i> {{ __('A manager PIN will be requested.') }}</div>@endcannot
                @else
                    <p class="text-body-secondary small mb-0">{{ __('Find a sale first.') }}</p>
                @endif
            </x-card>
        </div>
    </div>
    <x-manager-pin-modal :approval="$approval" />
</div>
