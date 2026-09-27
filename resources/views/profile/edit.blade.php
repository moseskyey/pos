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

        @if ($apiTokens !== null)
            <div class="col-12" id="api">
                <x-card :title="__('API tokens')" icon="bi-plug" :subtitle="__('Let a mobile app, online shop or accounting tool read your data. A token acts as you, with your permissions and branches.')">
                    @if ($plain = session('api_token_plain'))
                        <div class="alert alert-success" x-data="{ copied: false }">
                            <div class="fw-semibold mb-1"><i class="bi bi-key"></i> {{ __('Your new token (copy it now, it will not be shown again):') }}</div>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" value="{{ $plain }}" readonly x-ref="token" aria-label="{{ __('API token') }}" @focus="$el.select()">
                                <button type="button" class="btn btn-success" @click="navigator.clipboard.writeText($refs.token.value); copied = true"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i> <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span></button>
                            </div>
                        </div>
                    @endif
                    @if ($apiTokens->isNotEmpty())
                        <div class="table-responsive mb-3">
                            <table class="table table-stack align-middle mb-0">
                                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Token') }}</th><th>{{ __('Access') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                                <tbody>
                                @foreach ($apiTokens as $token)
                                    <tr>
                                        <td data-label="{{ __('Name') }}" class="fw-semibold">{{ $token->name }}</td>
                                        <td data-label="{{ __('Token') }}" class="font-monospace small">{{ $token->prefix }}…</td>
                                        <td data-label="{{ __('Access') }}">{{ $token->can('write') ? __('Read & write') : __('Read only') }}</td>
                                        <td data-label="{{ __('Last used') }}" class="small">{{ $token->last_used_at ? format_date($token->last_used_at, true) : __('Never') }}</td>
                                        <td data-label="{{ __('Expires') }}" class="small {{ $token->isExpired() ? 'text-danger' : '' }}">{{ $token->expires_at ? format_date($token->expires_at) : __('Never') }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('api-tokens.destroy', $token) }}" data-confirm="{{ __('Revoke :n? Apps using it stop working.', ['n' => $token->name]) }}">@csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('api-tokens.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4"><x-input name="name" :label="__('Token name')" :placeholder="__('e.g. Online shop')" required class="mb-0" /></div>
                        <div class="col-md-3"><x-select name="expires_in_days" :label="__('Expires')" :options="['' => __('Never'), 30 => __('In 30 days'), 90 => __('In 90 days'), 365 => __('In 1 year')]" class="mb-0" /></div>
                        <div class="col-md-3"><x-toggle name="write" :label="__('Allow changes (create customers)')" class="mb-0" /></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Create token') }}</button></div>
                    </form>
                    <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-book"></i> {{ __('Send it as a header: Authorization: Bearer <token>. Base URL: :u', ['u' => url('/api/v1')]) }}</p>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts.app>
