<x-layouts.guest :title="__('Forgot password')">
    <h1 class="h3 fw-bold mb-1">{{ __('Forgot your password?') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Enter your email and we will send you a reset link.') }}</p>
    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-input name="email" type="email" :label="__('Email')" autofocus required prefix="<i class='bi bi-envelope'></i>" class="mb-4" />
        <button class="btn btn-primary btn-lg w-100">{{ __('Email reset link') }}</button>
    </form>
    <div class="text-center mt-3"><a href="{{ route('login') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('Back to sign in') }}</a></div>
</x-layouts.guest>
