<div class="card-body border-top">
    <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-left-right text-primary"></i> {{ __('Default unit conversions') }}</h6>
    <p class="small text-body-secondary">{{ __('Used as suggestions when adding secondary units to products, e.g. 1 Carton = 24 Pieces.') }}</p>
    <form wire:submit="addConversion" class="row g-2 align-items-start mb-3">
        <div class="col-md-1 col-2 pt-2 text-end fw-semibold">1</div>
        <div class="col-md-3 col-10"><x-select wire:model="conversion.from_unit_id" :options="$unitOptions" :placeholder="__('Unit')" class="mb-0" aria-label="{{ __('From unit') }}" /></div>
        <div class="col-md-1 col-2 pt-2 text-center">=</div>
        <div class="col-md-2 col-4"><x-input wire:model="conversion.factor" type="number" step="0.0001" placeholder="24" class="mb-0" aria-label="{{ __('Factor') }}" /></div>
        <div class="col-md-3 col-6"><x-select wire:model="conversion.to_unit_id" :options="$unitOptions" :placeholder="__('Unit')" class="mb-0" aria-label="{{ __('To unit') }}" /></div>
        <div class="col-md-2"><button class="btn btn-soft-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button></div>
    </form>
    <div class="d-flex flex-wrap gap-2">
        @foreach ($conversions as $c)
            <span class="badge rounded-pill text-bg-info-soft fs-6 fw-medium d-inline-flex align-items-center gap-2">
                1 {{ $c->fromUnit->name }} = {{ qty($c->factor) }} {{ $c->toUnit->name }}
                <button type="button" class="btn-close btn-close-sm" style="font-size:.6rem" wire:click="deleteConversion({{ $c->id }})" aria-label="{{ __('Remove') }}"></button>
            </span>
        @endforeach
    </div>
</div>

<x-livewire-modal :show="$showForm" :title="$editingId ? __('Edit unit') : __('New unit')" size="sm" :save-new="! $editingId">
    <x-input wire:model="form.name" :label="__('Name')" required placeholder="{{ __('Carton') }}" />
    <x-input wire:model="form.short_name" :label="__('Symbol')" required placeholder="ctn" />
    <x-toggle wire:model="form.allow_decimal" :label="__('Allow decimal quantities')" :help="__('For kg, litre and other measured items.')" />
    <x-toggle wire:model="form.is_active" :label="__('Active')" class="mb-0" />
</x-livewire-modal>
