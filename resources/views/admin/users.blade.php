<x-layouts.admin :title="__('Users')" :breadcrumbs="[__('Users')]">
    <x-page-header :title="__('All users')" :subtitle="__('Find anyone by name, email or phone across every business. Open the business to reset a password or deactivate a user.')" />
    <livewire:admin.logins-table />
</x-layouts.admin>
