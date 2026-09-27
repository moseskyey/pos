<x-layouts.guest :title="__('Reset password')">
    <h1 class="h3 fw-bold mb-4">{{ __('Choose a new password') }}</h1>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" :label="__('Email')" :value="old('email', $email)" required />
        <x-input name="password" type="password" :label="__('New password')" autocomplete="new-password" required />
        <x-input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required class="mb-4" />
        <button class="btn btn-primary btn-lg w-100">{{ __('Reset password') }}</button>
    </form>
</x-layouts.guest>
