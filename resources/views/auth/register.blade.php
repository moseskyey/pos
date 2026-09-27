<x-layouts.guest :title="__('Create your business account')">
    <h1 class="h3 fw-bold mb-1">{{ __('Start selling today') }}</h1>
    <p class="text-body-secondary mb-4">
        @if ($trialDays > 0)
            {{ __('Try every feature free for :count days. No payment needed now.', ['count' => $trialDays]) }}
        @else
            {{ __('Create your business account.') }}
        @endif
    </p>

    <form method="POST" action="{{ route('register') }}" x-data="{ show: false, loading: false }" @submit="loading = true" novalidate>
        @csrf
        <x-input name="business_name" :label="__('Business name')" required autofocus maxlength="120"
                 placeholder="{{ __('e.g. Mama Neema Supermarket') }}" prefix="<i class='bi bi-shop'></i>" />
        <x-input name="owner_name" :label="__('Your name')" required maxlength="120" autocomplete="name" prefix="<i class='bi bi-person'></i>" />
        <div class="row g-2">
            <div class="col-sm-6"><x-input name="email" type="email" :label="__('Email')" required autocomplete="email" prefix="<i class='bi bi-envelope'></i>" /></div>
            <div class="col-sm-6"><x-input name="phone" type="tel" :label="__('Mobile number')" required autocomplete="tel" placeholder="0712 345 678" prefix="<i class='bi bi-phone'></i>" /></div>
        </div>
        <div class="row g-2">
            <div class="col-sm-6">
                <div class="mb-3">
                    <label for="password" class="form-label required">{{ __('Password') }}</label>
                    <div class="input-group has-validation">
                        <input :type="show ? 'text' : 'password'" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required minlength="8">
                        <button type="button" class="btn btn-outline-secondary" @click="show = !show" :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"><i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label required">{{ __('Confirm password') }}</label>
                    <input :type="show ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>
        </div>

        @if ($plans->isNotEmpty())
            <fieldset class="mb-3">
                <legend class="form-label fs-6">{{ __('Plan') }} <span class="text-body-secondary small fw-normal">({{ __('you can change it later') }})</span></legend>
                <div class="d-grid gap-2">
                    @foreach ($plans as $plan)
                        <label class="plan-option card mb-0 {{ $errors->has('plan_id') ? 'border-danger' : '' }}">
                            <span class="card-body py-2 px-3 d-flex align-items-center gap-3">
                                <input class="form-check-input mt-0" type="radio" name="plan_id" value="{{ $plan->id }}" @checked((string) old('plan_id', $plans->first()->id) === (string) $plan->id)>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="d-block fw-semibold">{{ $plan->name }}</span>
                                    <span class="d-block small text-body-secondary text-truncate">{{ $plan->description }}</span>
                                </span>
                                <span class="text-end text-nowrap"><span class="fw-bold">{{ money($plan->price) }}</span><span class="d-block small text-body-secondary">/ {{ $plan->intervalLabel() }}</span></span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('plan_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </fieldset>
        @endif

        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" @checked(old('terms')) required>
            <label class="form-check-label small" for="terms">{{ __('I agree to the terms of service and understand that my business data is stored securely by DukaPOS.') }}</label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="loading">
            <span class="spinner-border spinner-border-sm" x-show="loading" x-cloak></span>
            <span x-text="loading ? @js(__('Setting up your shop…')) : @js(__('Create account'))">{{ __('Create account') }}</span>
        </button>
    </form>

    <p class="text-center small text-body-secondary mt-4 mb-0">
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">{{ __('Sign in') }}</a>
    </p>
</x-layouts.guest>
