<x-layouts.guest :title="__('Sign in')">
    <h1 class="h3 fw-bold mb-1">{{ __('Welcome back') }} 👋</h1>
    <p class="text-body-secondary mb-4">{{ __('Sign in with your email or phone number.') }}</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" x-data="{ show: false, loading: false }" @submit="loading = true" novalidate>
        @csrf
        <x-input name="login" :label="__('Email or phone')" autocomplete="username" autofocus required
                 placeholder="you@shop.co.tz / 0712 345 678" prefix="<i class='bi bi-person'></i>" />

        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="password" class="form-label required">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" class="small text-decoration-none">{{ __('Forgot password?') }}</a>
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input :type="show ? 'text' : 'password'" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                <button type="button" class="btn btn-outline-secondary" @click="show = !show" :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                    <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="loading">
            <span class="spinner-border" x-show="loading" x-cloak></span>
            <span>{{ __('Sign in') }}</span> <i class="bi bi-arrow-right" x-show="!loading"></i>
        </button>
    </form>

    @if (app()->environment('local') && \App\Models\User::where('email', 'owner@dukapos.test')->exists())
        <div class="card bg-surface mt-4 border-dashed">
            <div class="card-body small py-3">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle text-primary"></i> {{ __('Demo accounts') }} <span class="text-body-secondary fw-normal">({{ __('password') }}: <code>password</code>)</span></div>
                <div class="text-body-secondary">owner@dukapos.test · manager@dukapos.test · cashier@dukapos.test · store@dukapos.test · accounts@dukapos.test</div>
            </div>
        </div>
    @endif
</x-layouts.guest>
