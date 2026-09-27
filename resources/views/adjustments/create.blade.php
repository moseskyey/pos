<x-layouts.app :title="__('New adjustment')" :breadcrumbs="[__('Adjustments') => route('adjustments.index'), __('New')]">
    <x-page-header :title="__('New stock adjustment')" :subtitle="current_branch()?->name" />
    <livewire:inventory.adjustment-form />
</x-layouts.app>
