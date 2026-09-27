<x-layouts.app :title="$status === 'layaway' ? __('Layaways') : __('Sales')" :breadcrumbs="[__('Sales')]">
    <x-page-header :title="$status === 'layaway' ? __('Layaways') : __('Sales')" :subtitle="$status === 'layaway' ? __('Open layaways with stock reserved and balance due.') : __('Every completed, voided and layaway sale.')">
        <div class="btn-group">
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary {{ ! $status ? 'active' : '' }}">{{ __('All sales') }}</a>
            @can('layaway.manage')<a href="{{ route('sales.index', ['status' => 'layaway']) }}" class="btn btn-outline-secondary {{ $status === 'layaway' ? 'active' : '' }}">{{ __('Layaways') }}</a>@endcan
        </div>
        @can('pos.access')<a href="{{ route('pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> {{ __('Open POS') }}</a>@endcan
    </x-page-header>
    <livewire:tables.sales-table :only-status="$status" />
</x-layouts.app>
