@php $editing = $supplier->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit supplier') : __('New supplier')" :breadcrumbs="[__('Suppliers') => route('suppliers.index'), $editing ? $supplier->name : __('New')]">
    <x-page-header :title="$editing ? $supplier->name : __('New supplier')" />
    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Supplier details')" icon="bi-building">
                    <div class="row">
                        <div class="col-md-8"><x-input name="name" :label="__('Company name')" :value="$supplier->name" required placeholder="Bakhresa Group Ltd" /></div>
                        <div class="col-md-4"><x-input name="contact_person" :label="__('Contact person')" :value="$supplier->contact_person" /></div>
                        <div class="col-md-6"><x-input name="phone" :label="__('Phone')" :value="$supplier->displayPhone()" prefix="<i class='bi bi-phone'></i>" /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$supplier->email" /></div>
                        <div class="col-md-4"><x-input name="tin" :label="__('TIN')" :value="$supplier->tin" /></div>
                        <div class="col-md-4"><x-input name="vrn" :label="__('VRN')" :value="$supplier->vrn" /></div>
                        <div class="col-md-4"><x-input name="payment_terms_days" type="number" min="0" :label="__('Payment terms')" :value="$supplier->payment_terms_days" :suffix="__('days')" required /></div>
                        <div class="col-12"><x-input name="address" :label="__('Address')" :value="$supplier->address" /></div>
                        <div class="col-12"><x-textarea name="notes" :label="__('Notes')" :value="$supplier->notes" rows="2" class="mb-0" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                @unless ($editing)
                    <x-card :title="__('Opening balance')"><x-input name="opening_balance" type="number" min="0" :label="__('Amount we currently owe')" prefix="TSh" class="mb-0" /></x-card>
                @endunless
                <x-card :title="__('Status')" class="mt-4"><x-toggle name="is_active" :label="__('Active')" :checked="$supplier->is_active" class="mb-0" /></x-card>
            </div>
        </div>
        <x-form-actions :cancel="$editing ? route('suppliers.show', $supplier) : route('suppliers.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
