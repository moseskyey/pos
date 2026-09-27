<x-layouts.app :title="__('Stock takes')" :breadcrumbs="[__('Inventory'), __('Stock takes')]">
    <x-page-header :title="__('Stock takes')" :subtitle="__('Full or category cycle counts with variance reports.')">
        @can('stock.take')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTake"><i class="bi bi-plus-lg"></i> {{ __('New stock take') }}</button>@endcan
    </x-page-header>
    <livewire:tables.stock-takes-table />

    @push('modals')
        <x-modal id="newTake" :title="__('Start a stock take')">
            <form method="POST" action="{{ route('stock-takes.store') }}" id="newTakeForm">@csrf
                <p class="small text-body-secondary">{{ __('Expected quantities are frozen when you start. Sales can continue; the variance posted is counted minus frozen quantity.') }}</p>
                <x-select name="category_id" :label="__('Scope')" :options="$categories" :placeholder="__('Full count (all products)')" searchable />
                <x-textarea name="note" :label="__('Note')" rows="2" class="mb-0" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary" form="newTakeForm">{{ __('Start counting') }}</button>
            </x-slot:footer>
        </x-modal>
    @endpush
</x-layouts.app>
