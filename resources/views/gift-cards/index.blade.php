<x-layouts.app :title="__('Gift cards')" :breadcrumbs="[__('Customers'), __('Gift cards')]">
    <x-page-header :title="__('Gift cards & vouchers')" :subtitle="__(':count issued · :o still to be spent', ['count' => number_format($count), 'o' => money($outstanding)])">
        <a href="{{ route('gift-cards.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Issue gift card') }}</a>
    </x-page-header>
    <livewire:tables.gift-cards-table />
</x-layouts.app>
