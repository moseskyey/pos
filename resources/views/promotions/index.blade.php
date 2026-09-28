<x-layouts.app :title="__('Promotions')" :breadcrumbs="[__('Sales'), __('Promotions')]">
    <x-page-header :title="__('Promotions')" :subtitle="trans_choice(':count promotion running now|:count promotions running now', $running)">
        <a href="{{ route('promotions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New promotion') }}</a>
    </x-page-header>
    <div class="alert alert-info small d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <div>{{ __('Promotions apply at the till automatically. When several match an item, the one that saves the customer most is used. They do not count towards the cashier discount limit.') }}</div>
    </div>
    <livewire:tables.promotions-table />
</x-layouts.app>
