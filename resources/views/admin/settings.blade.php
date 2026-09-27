<x-layouts.admin :title="__('Settings')" :breadcrumbs="[__('Settings')]">
    <x-page-header :title="__('Platform settings')" :subtitle="__('Trials, billing, payments and support contacts for every business.')" />
    <form method="POST" action="{{ route('admin.settings.update') }}" x-data="dirtyForm">
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('General')" icon="bi-sliders">
                    <div class="row">
                        <div class="col-md-6"><x-input name="name" :label="__('Platform name')" :value="$settings['name']" required maxlength="60" /></div>
                        <div class="col-md-6"><x-select name="default_plan_id" :label="__('Default plan for new sign-ups')" :options="$plans->all()" :value="$settings['default_plan_id']" :placeholder="__('None (trial only)')" /></div>
                        <div class="col-md-4"><x-input name="trial_days" type="number" min="0" max="365" :label="__('Free trial (days)')" :value="$settings['trial_days']" required /></div>
                        <div class="col-md-4"><x-input name="grace_days" type="number" min="0" max="60" :label="__('Grace period (days)')" :value="$settings['grace_days']" required :help="__('Working days allowed after expiry.')" /></div>
                        <div class="col-md-4"><x-input name="reminder_days" :label="__('Remind before expiry (days)')" :value="$settings['reminder_days']" placeholder="7,3,1" /></div>
                    </div>
                    <x-toggle name="signups_enabled" :label="__('Allow new businesses to sign up')" :checked="$settings['signups_enabled']" class="mb-0" />
                </x-card>

                <x-card :title="__('Subscription payments (FastLipa)')" icon="bi-phone-vibrate" class="mt-4" :subtitle="__('The platform\'s own FastLipa account, used when businesses pay for their subscription.')">
                    <x-toggle name="fastlipa_enabled" :label="__('Let businesses pay by mobile money')" :checked="$settings['fastlipa_enabled']" />
                    <div class="row">
                        <div class="col-md-6"><x-input name="fastlipa_api_key" type="password" :label="__('API key')" autocomplete="off" :placeholder="$settings['fastlipa_api_key'] ? '••••••••  '.__('(saved)') : ''" :help="__('Leave blank to keep the saved key.')" /></div>
                        <div class="col-md-6"><x-input name="fastlipa_webhook_secret" type="password" :label="__('Webhook secret (optional)')" autocomplete="off" :placeholder="$settings['fastlipa_webhook_secret'] ? '••••••••  '.__('(saved)') : ''" /></div>
                        <div class="col-12"><x-input name="fastlipa_base_url" type="url" :label="__('API URL')" :value="$settings['fastlipa_base_url']" required class="mb-2" /></div>
                    </div>
                    <div class="small text-body-secondary">{{ __('Callback URL') }}: <code class="user-select-all">{{ route('billing.callback') }}</code></div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Support contacts')" icon="bi-headset" :subtitle="__('Shown on every business\'s Subscription page and invoices.')">
                    <x-input name="support_phone" :label="__('Phone')" :value="$settings['support_phone']" placeholder="+255 712 345 678" />
                    <x-input name="support_whatsapp" :label="__('WhatsApp')" :value="$settings['support_whatsapp']" placeholder="255712345678" />
                    <x-input name="support_email" type="email" :label="__('Email')" :value="$settings['support_email']" />
                    <x-textarea name="billing_instructions" :label="__('Other ways to pay')" :value="$settings['billing_instructions']" rows="5" class="mb-0"
                                :help="__('Bank account, Lipa Namba, etc. Record these payments on the business page.')" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('admin.dashboard')" />
    </form>
</x-layouts.admin>
