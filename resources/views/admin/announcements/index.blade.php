<x-layouts.admin :title="__('Announcements')" :breadcrumbs="[__('Announcements')]">
    <x-page-header :title="__('Announcements')" :subtitle="__('Notices shown at the top of every page for all businesses, or one business.')">
        <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New announcement') }}</a>
    </x-page-header>
    <livewire:admin.announcements-table />
</x-layouts.admin>
