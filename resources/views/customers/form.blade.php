@php $editing = $customer->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit customer') : __('New customer')" :breadcrumbs="[__('Customers') => route('customers.index'), $editing ? $customer->name : __('New')]">
    <x-page-header :title="$editing ? $customer->name : __('New customer')" />
    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Contact details')" icon="bi-person">
                    <div class="row">
                        <div class="col-md-8"><x-input name="name" :label="__('Full name / business name')" :value="$customer->name" required /></div>
                        <div class="col-md-4"><x-select name="type" :label="__('Type')" :disabled="! auth()->user()->can('customers.credit')" :options="['retail' => __('Retail'), 'wholesale' => __('Wholesale')]" :value="$customer->type" :help="__('Wholesale customers get wholesale prices.')" /></div>
                        <div class="col-md-6"><x-input name="phone" :label="__('Phone')" :value="$customer->displayPhone()" placeholder="0712 345 678" prefix="<i class='bi bi-phone'></i>" /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$customer->email" /></div>
                        <div class="col-md-6"><x-input name="tin" :label="__('TIN')" :value="$customer->tin" /></div>
                        <div class="col-md-6"><x-input name="address" :label="__('Address')" :value="$customer->address" /></div>
                        <div class="col-12"><x-textarea name="notes" :label="__('Notes')" :value="$customer->notes" rows="2" class="mb-0" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Credit')" icon="bi-journal-text">
                    <x-input name="credit_limit" :disabled="! auth()->user()->can('customers.credit')" type="number" min="0" step="1000" :label="__('Credit limit')" :value="(float) $customer->credit_limit" prefix="TSh" :help="__('Maximum debt allowed. 0 = no credit without manager approval.')" />
                    @unless ($editing)
                        <x-input name="opening_balance" :disabled="! auth()->user()->can('customers.credit')" type="number" min="0" :label="__('Opening balance (existing debt)')" prefix="TSh" class="mb-0" />
                    @endunless
                </x-card>
                <x-card :title="__('Status')" class="mt-4">
                    <x-toggle name="is_active" :label="__('Active')" :checked="$customer->is_active" class="mb-0" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="$editing ? route('customers.show', $customer) : route('customers.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
