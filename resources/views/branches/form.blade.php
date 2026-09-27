@php $editing = $branch->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit branch') : __('New branch')" :breadcrumbs="[__('Branches') => route('branches.index'), $editing ? $branch->name : __('New')]">
    <x-page-header :title="$editing ? __('Edit :name', ['name' => $branch->name]) : __('New branch')" :subtitle="__('Branch details appear on receipts and documents.')" />

    <form method="POST" action="{{ $editing ? route('branches.update', $branch) : route('branches.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Branch details')" icon="bi-shop">
                    <div class="row">
                        <div class="col-md-8"><x-input name="name" :label="__('Branch name')" :value="$branch->name" required placeholder="Kariakoo" /></div>
                        <div class="col-md-4"><x-input name="code" :label="__('Code')" :value="$branch->code" required placeholder="DSM01" :help="__('Used in document numbers, e.g. INV-DSM01-000123')" /></div>
                        <div class="col-12"><x-input name="address" :label="__('Address')" :value="$branch->address" placeholder="Msimbazi St, Kariakoo, Dar es Salaam" /></div>
                        <div class="col-md-6"><x-input name="phone" :label="__('Phone')" :value="$branch->phone" placeholder="0712 345 678" prefix="<i class='bi bi-telephone'></i>" /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$branch->email" prefix="<i class='bi bi-envelope'></i>" /></div>
                    </div>
                </x-card>
                <x-card :title="__('Receipt overrides')" icon="bi-receipt" class="mt-4" :subtitle="__('Leave blank to use the business defaults.')">
                    <x-textarea name="receipt_header" :label="__('Receipt header')" :value="$branch->receipt_header" rows="2" />
                    <x-textarea name="receipt_footer" :label="__('Receipt footer')" :value="$branch->receipt_footer" rows="2" class="mb-0" />
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Status')">
                    <x-toggle name="is_active" :label="__('Branch is active')" :checked="$branch->is_active" :help="__('Inactive branches are hidden from the branch switcher and POS.')" class="mb-0" />
                </x-card>
                <div class="card mt-4 bg-primary-soft border-0">
                    <div class="card-body small">
                        <div class="fw-semibold mb-1"><i class="bi bi-lightbulb text-primary"></i> {{ __('Tip') }}</div>
                        {{ __('Each branch keeps its own stock, tills, shifts and document number sequence. A default till is created automatically.') }}
                    </div>
                </div>
            </div>
        </div>
        <x-form-actions :cancel="route('branches.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
