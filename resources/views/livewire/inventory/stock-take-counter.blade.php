<div x-data @scan-ok.window="window.dpBeep && window.dpBeep(true)">
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Products')" :value="number_format($stats['total'])" icon="bi-list-check" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Counted')" :value="number_format($stats['counted'])" icon="bi-check2-circle" color="success" :hint="$stats['total'] ? round($stats['counted'] / $stats['total'] * 100).'%' : null" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('With variance')" :value="number_format($stats['variance'])" icon="bi-exclamation-diamond" color="warning" /></div>
        @if ($canCost)
            <div class="col-6 col-xl-3"><x-stat-card :label="__('Variance value')" :value="money($stats['value'])" icon="bi-cash-stack" :color="$stats['value'] < 0 ? 'danger' : 'info'" /></div>
        @endif
    </div>

    <div class="card">
        @if ($take->isEditable())
            <div class="card-body border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-7">@include('livewire.partials.product-search', ['placeholder' => __('Scan a barcode to count it…')])</div>
                    <div class="col-lg-5">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="addMode" wire:model.live="addMode">
                            <label class="form-check-label" for="addMode">{{ __('Each scan adds 1 (turn off to set exact count)') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="filter-bar">
            <div class="search-input"><i class="bi bi-search"></i><input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('Filter list…') }}"></div>
            <div class="btn-group" role="group">
                @foreach (['all' => __('All'), 'uncounted' => __('Not counted'), 'variance' => __('Variance')] as $k => $label)
                    <button type="button" class="btn btn-sm {{ $filter === $k ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('filter', '{{ $k }}')">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle table-stack">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Expected') }}</th><th style="width:170px">{{ __('Counted') }}</th><th class="text-end">{{ __('Variance') }}</th>@if ($canCost)<th class="text-end">{{ __('Value') }}</th>@endif</tr></thead>
                <tbody>
                @forelse ($items as $item)
                    @php $variance = $item->variance(); @endphp
                    <tr wire:key="sti-{{ $item->id }}">
                        <td data-label="{{ __('Product') }}"><div class="fw-semibold">{{ $item->product->name }}</div><div class="small text-body-secondary font-monospace">{{ $item->product->sku }}</div></td>
                        <td data-label="{{ __('Expected') }}" class="text-end">{{ qty($item->expected_quantity) }} <span class="text-body-secondary small">{{ $item->product->unit?->short_name }}</span></td>
                        <td data-label="{{ __('Counted') }}">
                            @if ($take->isEditable())
                                <input type="number" step="0.001" min="0" class="form-control form-control-sm {{ $item->counted_quantity !== null ? 'border-success' : '' }}"
                                       placeholder="{{ $item->counted_quantity !== null ? qty($item->counted_quantity) : '—' }}"
                                       wire:model="counts.{{ $item->id }}" wire:keydown.enter="saveCount({{ $item->id }})" wire:blur="saveCount({{ $item->id }})"
                                       value="{{ $item->counted_quantity !== null ? qty($item->counted_quantity) : '' }}" aria-label="{{ __('Counted quantity') }}">
                            @else
                                {{ $item->counted_quantity !== null ? qty($item->counted_quantity) : '—' }}
                            @endif
                        </td>
                        <td data-label="{{ __('Variance') }}" class="text-end fw-semibold {{ $variance === null ? 'text-body-secondary' : ($variance < 0 ? 'text-danger' : ($variance > 0 ? 'text-success' : '')) }}">
                            {{ $variance === null ? '—' : ($variance > 0 ? '+' : '').qty($variance) }}
                        </td>
                        @if ($canCost)
                            <td data-label="{{ __('Value') }}" class="text-end text-money">{{ $variance === null ? '—' : money(\App\Support\Money::mul($variance, $item->unit_cost)) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-clipboard-check" :title="__('No items match')" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
    </div>
</div>
