<x-livewire-modal :show="$showForm" :title="$editingId ? __('Edit recurring expense') : __('New recurring expense')">
    <x-input wire:model="form.description" :label="__('Description')" required :placeholder="__('e.g. Shop rent – Kariakoo')" />
    <div class="row">
        <div class="col-md-6"><x-select wire:model="form.expense_category_id" :label="__('Category')" :options="$categories" :placeholder="__('Select')" required /></div>
        <div class="col-md-6"><x-input wire:model="form.amount" type="number" min="0" :label="__('Amount')" prefix="TSh" required /></div>
        <div class="col-md-6"><x-select wire:model="form.payment_method" :label="__('Method')" :options="$methods" /></div>
        <div class="col-md-6"><x-select wire:model="form.frequency" :label="__('Frequency')" :options="['monthly' => __('Monthly'), 'weekly' => __('Weekly')]" /></div>
        <div class="col-md-6"><x-input wire:model="form.next_run_date" type="date" :label="__('Next run')" required /></div>
        <div class="col-md-6 pt-md-4"><x-toggle wire:model="form.is_active" :label="__('Active')" /></div>
    </div>
</x-livewire-modal>
