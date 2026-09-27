<x-layouts.admin :title="__('Add business')" :breadcrumbs="[__('Businesses') => route('admin.tenants.index'), __('New')]">
    <x-page-header :title="__('Add business')" :subtitle="__('Creates the business with its own database, a first branch and till, and the owner account.')" />

    <form method="POST" action="{{ route('admin.tenants.store') }}" x-data="dirtyForm" @submit="$el.querySelector('[type=submit]').disabled = true">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Business')" icon="bi-shop">
                    <x-input name="business_name" :label="__('Business name')" required maxlength="120" placeholder="Mama Neema Supermarket" />
                    <x-textarea name="notes" :label="__('Internal notes')" rows="2" :help="__('Only platform admins see this.')" class="mb-0" />
                </x-card>
                <x-card :title="__('Owner account')" icon="bi-person-badge" class="mt-4">
                    <div class="row">
                        <div class="col-md-6"><x-input name="owner_name" :label="__('Owner name')" required maxlength="120" /></div>
                        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email (sign-in)')" required prefix="<i class='bi bi-envelope'></i>" /></div>
                        <div class="col-md-6"><x-input name="phone" type="tel" :label="__('Mobile number')" placeholder="0712 345 678" prefix="<i class='bi bi-phone'></i>" /></div>
                        <div class="col-md-6"><x-input name="password" type="password" :label="__('Password')" autocomplete="new-password" :help="__('Leave blank to generate one; it is shown once after saving.')" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Subscription')" icon="bi-stars">
                    <x-select name="plan_id" :label="__('Plan')" :options="$plans->all()" :value="$defaultPlan" :placeholder="__('No plan (trial only)')" />
                    <x-input name="trial_days" type="number" min="0" max="3650" :label="__('Free trial (days)')" :value="$trialDays" required class="mb-0" />
                </x-card>
                <div class="card mt-4 bg-primary-soft border-0">
                    <div class="card-body small">
                        <div class="fw-semibold mb-1"><i class="bi bi-lightbulb text-primary"></i> {{ __('Tip') }}</div>
                        {{ __('After a payment, record it on the business page to extend the subscription. Businesses can also pay by mobile money from their Subscription page.') }}
                    </div>
                </div>
            </div>
        </div>
        <x-form-actions :cancel="route('admin.tenants.index')" :label="__('Create business')" />
    </form>
</x-layouts.admin>
