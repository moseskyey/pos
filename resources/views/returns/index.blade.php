<x-layouts.app :title="__('Returns')" :breadcrumbs="[__('Sales'), __('Returns')]">
    <x-page-header :title="__('Returns & refunds')" :subtitle="__('Customer returns with restock or damaged handling.')">
        <a href="{{ route('returns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New return') }}</a>
    </x-page-header>
    <livewire:tables.returns-table />
</x-layouts.app>
