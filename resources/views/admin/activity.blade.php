<x-layouts.admin :title="__('Activity log')" :breadcrumbs="[__('Activity log')]">
    <x-page-header :title="__('Admin activity log')" :subtitle="__('Every change made by platform admins: sign-ins, payments, extensions, suspensions and settings.')" />
    <livewire:admin.activity-table />
</x-layouts.admin>
