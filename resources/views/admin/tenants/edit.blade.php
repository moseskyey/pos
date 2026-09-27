<x-layouts.admin :title="__('Edit business')" :breadcrumbs="[__('Businesses') => route('admin.tenants.index'), $tenant->name => route('admin.tenants.show', $tenant), __('Edit')]">
    <x-page-header :title="__('Edit :name', ['name' => $tenant->name])" :subtitle="__('Changes here are recorded in the admin activity log.')" />

    <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" x-data="dirtyForm">
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Business')" icon="bi-shop">
                    <x-input name="name" :label="__('Business name')" :value="$tenant->name" required maxlength="120" />
                    <div class="row">
                        <div class="col-md-4"><x-input name="owner_name" :label="__('Owner name')" :value="$tenant->owner_name" /></div>
                        <div class="col-md-4"><x-input name="owner_email" type="email" :label="__('Owner email')" :value="$tenant->owner_email" /></div>
                        <div class="col-md-4"><x-input name="owner_phone" :label="__('Owner phone')" :value="$tenant->owner_phone" /></div>
                    </div>
                    <div class="form-text mb-3 mt-n2">{{ __('These are billing contacts. Sign-in details are changed by the business under Users.') }}</div>
                    <x-textarea name="notes" :label="__('Internal notes')" :value="$tenant->notes" rows="3" class="mb-0" />
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Subscription')" icon="bi-stars">
                    <x-select name="plan_id" :label="__('Plan')" :options="$plans->all()" :value="$tenant->plan_id" :placeholder="__('No plan')" />
                    <x-input name="trial_ends_at" type="date" :label="__('Trial ends')" :value="$tenant->trial_ends_at?->toDateString()" />
                    <x-input name="paid_until" type="date" :label="__('Paid until')" :value="$tenant->paid_until?->toDateString()" class="mb-0" :help="__('Prefer “Record payment” or “Extend” so the history stays complete.')" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('admin.tenants.show', $tenant)" />
    </form>
</x-layouts.admin>
