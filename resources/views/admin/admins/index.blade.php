<x-layouts.admin :title="__('Admins')" :breadcrumbs="[__('Admins')]">
    <x-page-header :title="__('Platform admins')" :subtitle="__('Super admins manage settings and other admins; support admins manage businesses and payments.')">
        <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add admin') }}</a>
    </x-page-header>
    <livewire:admin.admins-table />
</x-layouts.admin>
