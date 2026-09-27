<x-layouts.app :title="__('My profile')">
    <x-page-header :title="__('My profile')" :subtitle="__('Your account, password, PIN and security.')" />

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <x-card :title="__('Profile')" icon="bi-person">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <x-avatar :user="$user" size="xl" />
                        <div class="flex-grow-1"><x-file-upload name="avatar" :help="__('PNG or JPG, up to 2MB')" class="mb-0" /></div>
                    </div>
                    <x-input name="name" :label="__('Full name')" :value="$user->name" required />
                    <x-input name="email" type="email" :label="__('Email')" :value="$user->email" required />
                    <x-input name="phone" :label="__('Phone')" :value="$user->phone ? \App\Support\PhoneNumber::display($user->phone) : null" />
                    <x-select name="locale" :label="__('Language')" :options="['en' => 'English', 'sw' => 'Kiswahili']" :value="$user->locale" />
                    <x-slot:footer><div class="text-end"><button class="btn btn-primary">{{ __('Save profile') }}</button></div></x-slot:footer>
                </x-card>
            </form>
        </div>
        <div class="col-lg-6">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PUT')
                <x-card :title="__('Change password')" icon="bi-key">
                    <x-input name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" required bag="password" />
                    <x-input name="password" type="password" :label="__('New password')" autocomplete="new-password" required bag="password" />
                    <x-input name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" required class="mb-0" />
                    <x-slot:footer><div class="text-end"><button class="btn btn-primary">{{ __('Update password') }}</button></div></x-slot:footer>
                </x-card>
            </form>

            <form method="POST" action="{{ route('profile.pin') }}" class="mt-4">
                @csrf @method('PUT')
                <x-card :title="__('PIN')" icon="bi-grid-3x3-gap" :subtitle="__('Unlocks the POS lock screen and approves overrides (managers).')">
                    <div class="row">
                        <div class="col-md-6"><x-input name="pin" type="password" inputmode="numeric" maxlength="6" :label="__('New PIN')" autocomplete="new-password" required bag="pin" /></div>
                        <div class="col-md-6"><x-input name="pin_confirmation" type="password" inputmode="numeric" maxlength="6" :label="__('Confirm PIN')" autocomplete="new-password" required /></div>
                        <div class="col-12"><x-input name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" required class="mb-0" bag="pin" /></div>
                    </div>
                    <x-slot:footer><div class="text-end"><button class="btn btn-primary">{{ $user->hasPin() ? __('Change PIN') : __('Set PIN') }}</button></div></x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="col-12">
            <x-card :title="__('Two-factor authentication')" icon="bi-shield-check" :subtitle="__('Protect your account with an authenticator app (Google Authenticator, Authy…).')">
                @if ($user->hasTwoFactorEnabled())
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="badge rounded-pill text-bg-success-soft status-badge">{{ __('Enabled') }}</span>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="d-flex gap-2 ms-auto">@csrf @method('DELETE')
                            <input type="password" name="current_password" class="form-control" placeholder="{{ __('Current password') }}" required>
                            <button class="btn btn-soft-danger text-nowrap">{{ __('Disable 2FA') }}</button>
                        </form>
                    </div>
                @elseif ($pendingSecret)
                    <div class="row g-4 align-items-center">
                        <div class="col-md-auto text-center"><div class="p-2 bg-white rounded-3 d-inline-block border">{!! $qr !!}</div></div>
                        <div class="col-md">
                            <p class="mb-2">{{ __('Scan this QR code, or enter the key manually:') }}</p>
                            <code class="d-block mb-3 user-select-all">{{ $pendingSecret }}</code>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex gap-2" style="max-width: 360px">@csrf
                                <input type="text" name="code" inputmode="numeric" maxlength="6" class="form-control @error('code', 'twofactor') is-invalid @enderror" placeholder="123456" required>
                                <button class="btn btn-primary">{{ __('Confirm') }}</button>
                            </form>
                            @error('code', 'twofactor')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="d-flex flex-wrap gap-2 align-items-center">@csrf
                        <span class="badge rounded-pill text-bg-secondary-soft status-badge me-auto">{{ __('Disabled') }}</span>
                        <input type="password" name="current_password" class="form-control w-auto" placeholder="{{ __('Current password') }}" required>
                        <button class="btn btn-primary">{{ __('Enable 2FA') }}</button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
