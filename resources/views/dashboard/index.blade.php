<x-layouts.app :title="__('Dashboard')">
    <x-page-header :title="__('Karibu, :name!', ['name' => explode(' ', auth()->user()->name)[0]])" :subtitle="__('Here is what is happening in your shop today.')" />
</x-layouts.app>
