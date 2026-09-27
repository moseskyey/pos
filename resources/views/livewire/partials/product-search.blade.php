<div class="position-relative" x-data="{ open: false }" @click.outside="open = false">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
        <input type="search" class="form-control" wire:model.live.debounce.250ms="productSearch" @focus="open = true" @input="open = true"
               wire:keydown.enter.prevent="pickFirst" placeholder="{{ $placeholder ?? __('Scan barcode or search product…') }}" aria-label="{{ __('Search product') }}" autocomplete="off">
    </div>
    @if (strlen(trim($productSearch)) >= 2)
        <div class="card shadow-lg position-absolute w-100 mt-1" style="z-index: 1050; max-height: 320px; overflow-y: auto" x-show="open">
            <div class="list-group list-group-flush">
                @forelse ($this->productResults as $p)
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" wire:click="pickProduct({{ $p->id }})" @click="open = false">
                        <span class="min-w-0"><span class="fw-medium">{{ $p->name }}</span> <span class="small text-body-secondary font-monospace">{{ $p->sku }}</span></span>
                        <span class="small text-body-secondary">{{ $p->unit?->short_name }}</span>
                    </button>
                @empty
                    <div class="list-group-item small text-body-secondary">{{ __('No products found') }}</div>
                @endforelse
            </div>
        </div>
    @endif
</div>
