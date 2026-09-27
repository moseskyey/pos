<x-layouts.app :title="__('Shifts')" :breadcrumbs="[__('Sales'), __('Shifts')]">
    <x-page-header :title="__('Shifts & cash')" :subtitle="__('Opening floats, cash movements and end-of-day reconciliation.')">
        @can('shifts.open')<a href="{{ route('shifts.current') }}" class="btn btn-primary"><i class="bi bi-cash-coin"></i> {{ __('My current shift') }}</a>@endcan
    </x-page-header>
    @if ($trend && count($trend['labels']))
        <x-card :title="__('Over / short by cashier — last 30 days')" class="mb-4">
            <div class="chart-box-sm"><canvas data-chart='@json($trend)'></canvas></div>
        </x-card>
    @endif
    <livewire:tables.shifts-table />
</x-layouts.app>
