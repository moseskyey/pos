<x-livewire-modal :show="$showForm" :title="$editingId ? __('Edit brand') : __('New brand')" size="sm" :save-new="! $editingId">
    <x-input wire:model="form.name" :label="__('Name')" required />
    <x-toggle wire:model="form.is_active" :label="__('Active')" class="mb-0" />
</x-livewire-modal>
