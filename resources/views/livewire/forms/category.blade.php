<x-livewire-modal :show="$showForm" :title="$editingId ? __('Edit category') : __('New category')" :save-new="! $editingId">
    <x-input wire:model="form.name" :label="__('Name')" required />
    <x-select wire:model="form.parent_id" :label="__('Parent category')" :options="$parents" :placeholder="__('None (top-level)')" />
    <x-input wire:model="form.description" :label="__('Description')" />
    <div class="row">
        <div class="col-6"><x-input wire:model="form.color" type="color" :label="__('Colour')" class="form-control-color" style="width:100%" /></div>
        <div class="col-6"><x-input wire:model="form.sort_order" type="number" :label="__('Sort order')" min="0" /></div>
    </div>
    <x-toggle wire:model="form.is_active" :label="__('Active')" class="mb-0" />
</x-livewire-modal>
