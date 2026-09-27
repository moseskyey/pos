<x-layouts.guest :title="__('Platform admin')">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge rounded-pill text-bg-warning-soft"><i class="bi bi-shield-lock"></i> {{ __('Platform admin') }}</span>
    </div>
    <h1 class="h3 fw-bold mb-1">{{ __('Admin sign in') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Manage businesses, subscriptions and the platform.') }}</p>

    <form method="POST" action="{{ route('admin.login') }}" x-data="{ show: false, loading: false }" @submit="loading = true" novalidate>
        @csrf
        <x-input name="email" type="email" :label="__('Email')" autocomplete="username" autofocus required prefix="<i class='bi bi-envelope'></i>" />
        <div class="mb-3">
            <label for="password" class="form-label required">{{ __('Password') }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input :type="show ? 'text' : 'password'" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                <button type="button" class="btn btn-outline-secondary" @click="show = !show" :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"><i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="loading">
            <span class="spinner-border spinner-border-sm" x-show="loading" x-cloak></span> {{ __('Sign in') }}
        </button>
    </form>
    <p class="text-center small text-body-secondary mt-4 mb-0"><a href="{{ route('login') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('Shop sign in') }}</a></p>
</x-layouts.guest>
