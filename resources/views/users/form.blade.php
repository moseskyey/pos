@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit user') : __('New user')" :breadcrumbs="[__('Users') => route('users.index'), $editing ? $user->name : __('New')]">
    <x-page-header :title="$editing ? __('Edit :name', ['name' => $user->name]) : __('New user')" :subtitle="__('Users sign in with email or phone and password.')" />

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" enctype="multipart/form-data" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Account')" icon="bi-person">
                    <div class="row">
                        <div class="col-12"><x-input name="name" :label="__('Full name')" :value="$user->name" required /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$user->email" required prefix="<i class='bi bi-envelope'></i>" /></div>
                        <div class="col-md-6"><x-input name="phone" :label="__('Phone')" :value="$user->phone ? \App\Support\PhoneNumber::display($user->phone) : null" placeholder="0712 345 678" prefix="<i class='bi bi-phone'></i>" /></div>
                        <div class="col-md-6"><x-input name="password" type="password" :label="__('Password')" autocomplete="new-password" :required="! $editing" :help="$editing ? __('Leave blank to keep the current password.') : __('At least 8 characters.')" /></div>
                        <div class="col-md-6"><x-input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Role & branches')" icon="bi-shield-lock" class="mt-4">
                    <div class="row">
                        <div class="col-md-6"><x-select name="role" :label="__('Role')" :options="$roles" :value="$user->roles->first()?->name" :placeholder="__('Select role')" required /></div>
                        <div class="col-md-6"><x-select name="default_branch_id" :label="__('Default branch')" :options="$branches" :value="$user->default_branch_id" :placeholder="__('First assigned branch')" /></div>
                        <div class="col-12"><x-select name="branches[]" :label="__('Branch access')" :options="$branches" :value="$user->branches->pluck('id')->all()" multiple searchable required :help="__('Users only see data for these branches unless their role can view all branches.')" /></div>
                    </div>
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card :title="__('Profile photo')">
                    <x-file-upload name="avatar" :current="$user->avatarUrl()" :help="__('PNG or JPG, up to 2MB')" class="mb-0" />
                </x-card>
                <x-card :title="__('Manager PIN')" class="mt-4">
                    <x-input name="pin" type="password" inputmode="numeric" maxlength="6" :label="__('PIN (4–6 digits)')" autocomplete="new-password"
                             :help="$user->hasPin() ? __('A PIN is set. Enter a new one to change it.') : __('Used to approve voids, discounts and other overrides.')" class="mb-0" />
                </x-card>
                <x-card :title="__('Status')" class="mt-4">
                    <x-toggle name="is_active" :label="__('Account is active')" :checked="$user->is_active" :help="__('Inactive users cannot sign in.')" class="mb-0" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('users.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
