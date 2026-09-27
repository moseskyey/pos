@if ($generated = session('generated_password'))
    <div class="alert alert-warning d-flex gap-3 align-items-start" role="alert">
        <i class="bi bi-key fs-4"></i>
        <div>
            <strong>{{ __('New password — share it securely, it is shown only once.') }}</strong>
            <div class="mt-1">{{ $generated['email'] }} · <code class="user-select-all fs-6">{{ $generated['password'] }}</code></div>
            <div class="small text-body-secondary">{{ __('Ask the user to change it from My profile after signing in.') }}</div>
        </div>
    </div>
@endif
