<x-layouts.app :title="__('Users')" :breadcrumbs="[__('Settings'), __('Users')]">
    <x-page-header :title="__('Users')" :subtitle="__('Staff accounts, roles and branch access.')">
        @can('users.manage')
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> {{ __('Add user') }}</a>
        @endcan
    </x-page-header>
    <livewire:tables.users-table />
</x-layouts.app>
