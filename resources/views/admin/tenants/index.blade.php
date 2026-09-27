<x-layouts.admin :title="__('Businesses')" :breadcrumbs="[__('Businesses')]">
    <x-page-header :title="__('Businesses')" :subtitle="__('Every shop on the platform, its plan and subscription status.')">
        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add business') }}</a>
    </x-page-header>
    <livewire:admin.tenants-table />
</x-layouts.admin>
