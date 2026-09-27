<x-layouts.admin :title="__('Plans')" :breadcrumbs="[__('Plans')]">
    <x-page-header :title="__('Plans')" :subtitle="__('Prices and limits businesses subscribe to. Changes apply to renewals; limits apply at once.')">
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add plan') }}</a>
    </x-page-header>
    <livewire:admin.plans-table />
</x-layouts.admin>
