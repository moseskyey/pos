<x-layouts.app :title="__('Activity log')" :breadcrumbs="[__('Settings'), __('Activity log')]">
    <x-page-header :title="__('Activity log')" :subtitle="__('Audit trail of changes, overrides, logins and settings updates.')" />
    <livewire:tables.activity-table />
</x-layouts.app>
