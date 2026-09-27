@php $editing = $admin->exists; $self = $editing && $admin->is(auth('admin')->user()); @endphp
<x-layouts.admin :title="$editing ? __('Edit admin') : __('Add admin')" :breadcrumbs="[__('Admins') => route('admin.admins.index'), $editing ? $admin->name : __('New')]">
    <x-page-header :title="$editing ? $admin->name : __('Add admin')" :subtitle="$editing ? $admin->email : __('They sign in at :url', ['url' => route('admin.login')])">
        @if ($editing && ! $self)
            <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" data-confirm="{{ __('Remove :name as an admin?', ['name' => $admin->name]) }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('Remove') }}</button></form>
        @endif
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('admin.admins.update', $admin) : route('admin.admins.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Account')" icon="bi-person-gear">
                    <div class="row">
                        <div class="col-md-6"><x-input name="name" :label="__('Name')" :value="$admin->name" required /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$admin->email" required /></div>
                        <div class="col-md-6"><x-input name="password" type="password" :label="$editing ? __('New password') : __('Password')" :required="! $editing" autocomplete="new-password" :help="$editing ? __('Leave blank to keep the current password.') : __('At least 10 characters.')" class="mb-0" /></div>
                        <div class="col-md-6"><x-input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" class="mb-0" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Access')">
                    @if ($self)
                        <p class="small text-body-secondary mb-0">{{ __('You cannot change your own access level.') }}</p>
                        <input type="hidden" name="is_super" value="1"><input type="hidden" name="is_active" value="1">
                    @else
                        <x-toggle name="is_super" :label="__('Super admin')" :checked="$admin->is_super" :help="__('Can change platform settings, FastLipa keys, admins and refunds.')" />
                        <x-toggle name="is_active" :label="__('Active')" :checked="$admin->is_active" class="mb-0" />
                    @endif
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('admin.admins.index')" />
    </form>
</x-layouts.admin>
