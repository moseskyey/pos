<x-layouts.app :title="__('Branches')" :breadcrumbs="[__('Settings'), __('Branches')]">
    <x-page-header :title="__('Branches')" :subtitle="__('Shop locations, their tills and assigned staff.')">
        @can('branches.manage')
            <a href="{{ route('branches.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add branch') }}</a>
        @endcan
    </x-page-header>
    <livewire:tables.branches-table />
</x-layouts.app>
