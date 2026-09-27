<form wire:submit="save">
    <div class="row g-4">
        <div class="col-lg-8">
            <x-card :title="__('Products to transfer')" icon="bi-box-seam">
                <div class="mb-3">@include('livewire.partials.product-search')</div>
                @error('items')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                @if (! $items)
                    <x-empty-state icon="bi-truck" :title="__('Add products to transfer')" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-stack">
                            <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Available at source') }}</th><th style="width:140px">{{ __('Quantity') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($items as $i => $item)
                                @php $avail = (float) ($available[$item['product_id']] ?? 0); @endphp
                                <tr wire:key="trf-{{ $i }}">
                                    <td data-label="{{ __('Product') }}" class="fw-semibold">{{ $item['name'] }}</td>
                                    <td data-label="{{ __('Available at source') }}" class="text-end {{ $avail < (float) $item['quantity'] ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ qty($avail) }} {{ $item['unit'] }}</td>
                                    <td data-label="{{ __('Quantity') }}"><input type="number" step="0.001" min="0" class="form-control form-control-sm @error('items.'.$i.'.quantity') is-invalid @enderror" wire:model.live.debounce.400ms="items.{{ $i }}.quantity"></td>
                                    <td class="text-end" data-label=""><button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeItem({{ $i }})"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Route')" icon="bi-signpost-split">
                <x-select wire:model.live="from_branch_id" :label="__('From branch')" :options="$fromBranches" :placeholder="__('Select')" required />
                <div class="text-center text-body-secondary mb-2"><i class="bi bi-arrow-down fs-4"></i></div>
                <x-select wire:model="to_branch_id" :label="__('To branch')" :options="$toBranches" :placeholder="__('Select')" required />
                <x-textarea wire:model="note" :label="__('Note')" rows="2" class="mb-0" />
            </x-card>
            <div class="card mt-4 bg-primary-soft border-0"><div class="card-body small">
                <div class="fw-semibold mb-1"><i class="bi bi-diagram-3 text-primary"></i> {{ __('Workflow') }}</div>
                {{ __('Request → Approve → Dispatch (stock leaves source) → Receive (stock arrives, discrepancies recorded).') }}
            </div></div>
        </div>
    </div>
    <div class="sticky-actions">
        <a href="{{ route('transfers.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary px-4" wire:loading.attr="disabled"><span wire:loading wire:target="save" class="spinner-border"></span> <i class="bi bi-send"></i> {{ __('Create transfer') }}</button>
    </div>
</form>
