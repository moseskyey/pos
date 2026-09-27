<x-layouts.guest :title="__('Two-factor authentication')">
    <div class="rounded-circle bg-primary-soft text-primary d-grid mb-3" style="width:56px;height:56px;place-items:center;font-size:1.5rem"><i class="bi bi-shield-check"></i></div>
    <h1 class="h3 fw-bold mb-1">{{ __('Two-factor authentication') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
    <form method="POST" action="{{ route('two-factor.store') }}">
        @csrf
        <x-input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required class="mb-4" />
        <button class="btn btn-primary btn-lg w-100">{{ __('Verify') }}</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf
        <button class="btn btn-link text-body-secondary">{{ __('Use a different account') }}</button>
    </form>
</x-layouts.guest>
