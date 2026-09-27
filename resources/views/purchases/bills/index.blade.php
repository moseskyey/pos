<x-layouts.app :title="__('Supplier bills')" :breadcrumbs="[__('Purchases'), __('Supplier bills')]">
    <x-page-header :title="__('Supplier bills')" :subtitle="__('Accounts payable with due dates and aging.')">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#billModal"><i class="bi bi-plus-lg"></i> {{ __('Record bill') }}</button>
        <a href="{{ route('supplier-payments.create') }}" class="btn btn-primary"><i class="bi bi-cash"></i> {{ __('Pay supplier') }}</a>
    </x-page-header>
    <livewire:tables.supplier-bills-table />
    @push('modals')
        <x-modal id="billModal" :title="__('Record a supplier bill')">
            <form method="POST" action="{{ route('supplier-bills.store') }}" id="billForm">@csrf
                <x-select name="supplier_id" :label="__('Supplier')" :options="$suppliers" :placeholder="__('Select')" required />
                <div class="row">
                    <div class="col-md-6"><x-input name="bill_no" :label="__('Bill / invoice no.')" /></div>
                    <div class="col-md-6"><x-input name="bill_date" type="date" :label="__('Bill date')" :value="today()->toDateString()" required /></div>
                    <div class="col-md-6"><x-input name="total" type="number" min="0" :label="__('Total (incl. VAT)')" prefix="TSh" required /></div>
                    <div class="col-md-6"><x-input name="tax_total" type="number" min="0" :label="__('VAT included')" prefix="TSh" /></div>
                    <div class="col-md-6"><x-input name="due_date" type="date" :label="__('Due date')" :help="__('Defaults to supplier terms.')" /></div>
                </div>
                <x-textarea name="description" :label="__('Description')" rows="2" class="mb-0" />
            </form>
            <x-slot:footer><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" form="billForm">{{ __('Save bill') }}</button></x-slot:footer>
        </x-modal>
    @endpush
</x-layouts.app>
