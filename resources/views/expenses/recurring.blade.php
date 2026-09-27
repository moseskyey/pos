<x-layouts.app :title="__('Recurring expenses')" :breadcrumbs="[__('Expenses') => route('expenses.index'), __('Recurring')]">
    <x-page-header :title="__('Recurring expenses')" :subtitle="__('Templates are posted automatically every morning when due.')">
        <button class="btn btn-primary" onclick="Livewire.dispatch('open-create')"><i class="bi bi-plus-lg"></i> {{ __('Add template') }}</button>
    </x-page-header>
    <livewire:tables.recurring-expenses-table />
</x-layouts.app>
