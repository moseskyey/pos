@php
    $f = fn ($key) => str_replace('.', '_', $key);
@endphp
<x-layouts.app :title="__('Settings')" :breadcrumbs="[__('Settings'), __($groups[$group][0])]">
    <x-page-header :title="__('Settings')" :subtitle="__('Configure your business, POS behaviour and integrations.')" />

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body p-2">
                    <nav class="nav nav-pills nav-pills-soft flex-column gap-1">
                        @foreach ($groups as $key => [$label, $icon])
                            <a href="{{ route('settings.edit', $key) }}" class="nav-link d-flex align-items-center gap-2 {{ $group === $key ? 'active' : '' }}">
                                <i class="bi {{ $icon }}"></i> {{ __($label) }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <form method="POST" action="{{ route('settings.update', $group) }}" enctype="multipart/form-data" x-data="dirtyForm">
                @csrf @method('PUT')

                @switch($group)
                    @case('business')
                        <x-card :title="__('Business profile')" icon="bi-shop" :subtitle="__('Shown on receipts, invoices and reports.')">
                            <div class="row">
                                <div class="col-md-8"><x-input :name="$f('business.name')" :label="__('Business name')" :value="$s['business.name']" required /></div>
                                <div class="col-md-4"><x-input :name="$f('business.phone')" :label="__('Phone')" :value="$s['business.phone']" /></div>
                                <div class="col-md-6"><x-input :name="$f('business.tin')" :label="__('TIN')" :value="$s['business.tin']" placeholder="123-456-789" /></div>
                                <div class="col-md-6"><x-input :name="$f('business.vrn')" :label="__('VRN')" :value="$s['business.vrn']" placeholder="40-123456-A" /></div>
                                <div class="col-md-6"><x-input :name="$f('business.email')" type="email" :label="__('Email')" :value="$s['business.email']" /></div>
                                <div class="col-md-6"><x-input :name="$f('business.address')" :label="__('Address')" :value="$s['business.address']" /></div>
                                <div class="col-md-6">
                                    <x-file-upload name="logo" :label="__('Logo')" :current="$s['business.logo'] ? route('files.show', ['path' => $s['business.logo']]) : null" :help="__('PNG or JPG, up to 2MB')" class="mb-0" />
                                </div>
                            </div>
                        </x-card>
                        @break

                    @case('features')
                        <x-card :title="__('Quick setup by business type')" icon="bi-magic" :subtitle="__('Switches on the modules that suit your kind of shop. You can still change each one below.')">
                            <div class="row g-2">
                                @foreach ($presets as $key => $preset)
                                    <div class="col-6 col-md-4 col-xl-3">
                                        <button type="submit" form="preset-{{ $key }}" class="btn w-100 h-100 py-3 {{ $s['features.business_type'] === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
                                                @if ($s['features.business_type'] === $key) aria-pressed="true" @endif>
                                            <i class="bi {{ $preset['icon'] }} d-block fs-4 mb-1"></i>
                                            <span class="small fw-semibold">{{ __($preset['label']) }}</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                        <x-card :title="__('Modules')" icon="bi-toggles" class="mt-4" :subtitle="__('Switched-off modules disappear from menus and screens. Existing records are kept.')">
                            <div class="row">
                                @foreach ($features as $key => $feature)
                                    @php $settingKey = \App\Support\Features::settingKey($key); @endphp
                                    <div class="col-md-6">
                                        <div class="d-flex gap-3 align-items-start border rounded-3 p-3 mb-3">
                                            <span class="feature-icon bg-primary-soft text-primary" aria-hidden="true"><i class="bi {{ $feature['icon'] }}"></i></span>
                                            <div class="flex-grow-1">
                                                <x-toggle :name="$f($settingKey)" :label="__($feature['label'])" :checked="$s[$settingKey] ?? true" :help="__($feature['description'])" class="mb-0" />
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                        @break

                    @case('currency')
                        <x-card :title="__('Currency & tax')" icon="bi-currency-exchange">
                            <div class="row">
                                <div class="col-md-4"><x-input :name="$f('currency.symbol')" :label="__('Currency symbol')" :value="$s['currency.symbol']" required /></div>
                                <div class="col-md-4"><x-select :name="$f('currency.decimals')" :label="__('Decimal places')" :options="[0 => '0 (TSh 12,500)', 2 => '2 (TSh 12,500.00)']" :value="$s['currency.decimals']" /></div>
                                <div class="col-md-4"><x-input :name="$f('currency.thousands_separator')" :label="__('Thousands separator')" :value="$s['currency.thousands_separator']" maxlength="1" /></div>
                                <div class="col-md-4"><x-input :name="$f('currency.usd_rate')" type="number" step="0.01" :label="__('USD exchange rate')" :value="$s['currency.usd_rate']" prefix="1 US$ =" suffix="TSh" :help="__('Used for Cash (USD) payments. Change is given in shillings.')" /></div>
                                <div class="col-md-4"><x-input :name="$f('tax.vat_rate')" type="number" step="0.01" :label="__('VAT rate')" :value="$s['tax.vat_rate']" suffix="%" required /></div>
                                <div class="col-md-8 pt-md-4"><x-toggle :name="$f('tax.prices_include_vat')" :label="__('Selling prices include VAT')" :checked="$s['tax.prices_include_vat']" :help="__('Recommended for retail. VAT is extracted from the price on receipts.')" /></div>
                            </div>
                            <div class="alert alert-info small mb-0"><i class="bi bi-info-circle"></i> {{ __('Preview:') }} <strong>{{ money(1234567) }}</strong></div>
                        </x-card>
                        @break

                    @case('receipt')
                        <x-card :title="__('Receipts')" icon="bi-receipt">
                            <div class="row">
                                <div class="col-md-4"><x-select :name="$f('receipt.paper')" :label="__('Paper size')" :options="['80mm' => '80mm', '58mm' => '58mm']" :value="$s['receipt.paper']" /></div>
                                <div class="col-12"><x-textarea :name="$f('receipt.header')" :label="__('Header message')" :value="$s['receipt.header']" rows="2" /></div>
                                <div class="col-12"><x-textarea :name="$f('receipt.footer')" :label="__('Footer message')" :value="$s['receipt.footer']" rows="2" /></div>
                                <div class="col-md-4"><x-toggle :name="$f('receipt.show_logo')" :label="__('Show logo')" :checked="$s['receipt.show_logo']" /></div>
                                <div class="col-md-4"><x-toggle :name="$f('receipt.show_qr')" :label="__('Show QR code')" :checked="$s['receipt.show_qr']" /></div>
                                <div class="col-md-4"><x-toggle :name="$f('receipt.auto_print')" :label="__('Auto-print after sale')" :checked="$s['receipt.auto_print']" /></div>
                                <div class="col-md-6"><x-select :name="$f('receipt.print_mode')" :label="__('Printing')" :options="['browser' => __('Browser print dialog'), 'escpos' => __('Direct to thermal printer (ESC/POS)')]" :value="$s['receipt.print_mode']"
                                          :help="__('Direct printing works in Chrome or Edge with a USB or serial (COM) receipt printer. Connect it once from the POS screen.')" /></div>
                                <div class="col-md-6 pt-md-4"><x-toggle :name="$f('receipt.drawer_kick')" :label="__('Open cash drawer on cash sales')" :checked="$s['receipt.drawer_kick']" :help="__('The drawer must be plugged into the receipt printer.')" /></div>
                            </div>
                        </x-card>
                        @break

                    @case('pos')
                        <x-card :title="__('POS behaviour')" icon="bi-upc-scan">
                            <div class="row">
                                <div class="col-md-6"><x-select :name="$f('pos.negative_stock')" :label="__('Selling beyond stock')" :options="['block' => __('Block'), 'warn' => __('Warn (manager PIN)'), 'allow' => __('Allow negative stock')]" :value="$s['pos.negative_stock']" /></div>
                                <div class="col-md-6"><x-select :name="$f('pos.below_cost')" :label="__('Selling below cost')" :options="['block' => __('Block'), 'approval' => __('Require manager PIN'), 'allow' => __('Allow')]" :value="$s['pos.below_cost']" /></div>
                                <div class="col-md-4"><x-input :name="$f('pos.max_discount_percent')" type="number" step="0.01" :label="__('Max cashier discount')" :value="$s['pos.max_discount_percent']" suffix="%" /></div>
                                <div class="col-md-4"><x-select :name="$f('pos.rounding')" :label="__('Round totals to')" :options="['0' => __('No rounding'), '50' => 'TSh 50', '100' => 'TSh 100']" :value="(string) $s['pos.rounding']" /></div>
                                <div class="col-md-4"><x-input :name="$f('pos.lock_minutes')" type="number" :label="__('Lock POS after idle')" :value="$s['pos.lock_minutes']" :suffix="__('min')" :help="__('0 disables the lock screen.')" /></div>
                                <div class="col-md-6"><x-select :name="$f('pos.default_customer_id')" :label="__('Default customer')" :options="$customers" :value="$s['pos.default_customer_id']" :placeholder="__('Walk-in customer')" searchable /></div>
                                <div class="col-md-6 pt-md-4"><x-toggle :name="$f('pos.scan_sound')" :label="__('Beep on barcode scan')" :checked="$s['pos.scan_sound']" /></div>
                            </div>
                        </x-card>
                        <x-card :title="__('Inventory')" icon="bi-box-seam" class="mt-4">
                            <div class="row">
                                <div class="col-md-6"><x-select :name="$f('inventory.costing')" :label="__('Cost price method on GRN')" :options="['average' => __('Moving average cost'), 'last' => __('Last purchase cost')]" :value="$s['inventory.costing']" /></div>
                                <div class="col-md-6"><x-input :name="$f('inventory.expiry_alert_days')" type="number" :label="__('Expiry alert window')" :value="$s['inventory.expiry_alert_days']" :suffix="__('days')" /></div>
                            </div>
                        </x-card>
                        @if (feature('credit_terms'))
                            <x-card :title="__('Credit terms')" icon="bi-calendar-check" class="mt-4" :subtitle="__('Credit sales are due this many days after the sale, unless the customer has their own terms.')">
                                <div class="row">
                                    <div class="col-md-6"><x-input :name="$f('credit.default_days')" type="number" min="0" max="365" :label="__('Default payment terms')" :value="$s['credit.default_days']" :suffix="__('days')" /></div>
                                </div>
                            </x-card>
                        @endif
                        <x-card :title="__('Loyalty points')" icon="bi-gift" class="mt-4">
                            <div class="row">
                                <div class="col-12"><p class="small text-muted"><i class="bi bi-toggles"></i> {{ $s['loyalty.enabled'] ? __('Loyalty points are on.') : __('Loyalty points are off.') }} <a href="{{ route('settings.edit', 'features') }}">{{ __('Change in Features') }}</a></p></div>
                                <div class="col-md-6"><x-input :name="$f('loyalty.earn_per_amount')" type="number" :label="__('Earn 1 point per')" :value="$s['loyalty.earn_per_amount']" prefix="TSh" /></div>
                                <div class="col-md-6"><x-input :name="$f('loyalty.point_value')" type="number" step="0.01" :label="__('Value of 1 point when redeemed')" :value="$s['loyalty.point_value']" prefix="TSh" /></div>
                            </div>
                        </x-card>
                        @break

                    @case('payments')
                        <x-card :title="__('Payment methods')" icon="bi-credit-card">
                            <div class="row">
                                @foreach (['cash' => 'Cash', 'cash_usd' => 'Cash (USD)', 'mpesa' => 'M-Pesa', 'tigopesa' => 'Mixx by Yas (Tigo Pesa)', 'airtel' => 'Airtel Money', 'halopesa' => 'HaloPesa', 'card' => 'Card', 'bank' => 'Bank transfer', 'credit' => 'Credit (on account)', 'store_credit' => 'Store credit'] as $key => $label)
                                    <div class="col-md-4"><x-toggle :name="$f('payments.'.$key)" :label="__($label)" :checked="$s['payments.'.$key]" /></div>
                                @endforeach
                            </div>
                        </x-card>
                        <x-card :title="__('Mobile money gateway')" icon="bi-phone" class="mt-4" :subtitle="__('Manual reference entry is always available.')">
                            <div class="row">
                                <div class="col-md-6"><x-select :name="$f('payments.gateway')" :label="__('STK push driver')" :options="['manual' => __('Manual (no STK push)'), 'fastlipa' => 'FastLipa']" :value="$s['payments.gateway']" /></div>
                                <div class="col-md-6"><x-input :name="$f('payments.fastlipa_base_url')" :label="__('FastLipa API URL')" :value="$s['payments.fastlipa_base_url']" /></div>
                                <div class="col-md-6"><x-input :name="$f('payments.fastlipa_api_key')" type="password" :label="__('FastLipa API key')" autocomplete="off"
                                             :help="$s['payments.fastlipa_api_key'] ? __('A key is saved (encrypted). Leave blank to keep it.') : __('Stored encrypted.')" class="mb-0" /></div>
                                <div class="col-md-6"><x-input :name="$f('payments.fastlipa_webhook_secret')" type="password" :label="__('Webhook signing secret')" autocomplete="off"
                                             :help="$s['payments.fastlipa_webhook_secret'] ? __('Saved. Leave blank to keep.') : __('Optional. Every callback is confirmed with FastLipa’s status API before it counts.')" class="mb-0" /></div>
                                <div class="col-12 mt-3"><div class="small text-body-secondary">{{ __('Callback URL') }}: <code class="user-select-all">{{ route('payments.callback', 'fastlipa') }}</code></div></div>
                            </div>
                        </x-card>
                        @break

                    @case('notifications')
                        <x-card :title="__('SMS gateway')" icon="bi-chat-dots">
                            <div class="row">
                                <div class="col-md-6"><x-select :name="$f('sms.driver')" :label="__('Driver')" :options="['log' => __('Log only (testing)'), 'beem' => 'Beem Africa']" :value="$s['sms.driver']" /></div>
                                <div class="col-md-6"><x-input :name="$f('sms.sender_id')" :label="__('Sender ID')" :value="$s['sms.sender_id']" maxlength="11" /></div>
                                <div class="col-md-6"><x-input :name="$f('sms.api_key')" type="password" :label="__('API key')" autocomplete="off" :help="$s['sms.api_key'] ? __('Saved. Leave blank to keep.') : null" /></div>
                                <div class="col-md-6"><x-input :name="$f('sms.api_secret')" type="password" :label="__('API secret')" autocomplete="off" :help="$s['sms.api_secret'] ? __('Saved. Leave blank to keep.') : null" /></div>
                            </div>
                        </x-card>
                        <x-card :title="__('Alerts')" icon="bi-bell" class="mt-4">
                            <div class="row">
                                <div class="col-md-6"><x-toggle :name="$f('notify.low_stock_email')" :label="__('Daily low-stock email to managers')" :checked="$s['notify.low_stock_email']" /></div>
                                <div class="col-md-6"><x-toggle :name="$f('notify.low_stock_sms')" :label="__('Daily low-stock SMS to managers')" :checked="$s['notify.low_stock_sms']" /></div>
                                <div class="col-md-6"><x-input :name="$f('notify.over_short_threshold')" type="number" :label="__('Alert when shift over/short exceeds')" :value="$s['notify.over_short_threshold']" prefix="TSh" /></div>
                            </div>
                        </x-card>
                        <x-card :title="__('TRA fiscal device (VFD/EFD)')" icon="bi-bank" class="mt-4">
                            <x-select :name="$f('fiscal.driver')" :label="__('Driver')" :options="['null' => __('None (not connected)')]" :value="$s['fiscal.driver']"
                                      :help="__('Extension point for TRA VFD integration. Receipts are submitted through the FiscalDevice interface.')" class="mb-0" />
                        </x-card>
                        @break

                    @case('localisation')
                        <x-card :title="__('Language & region')" icon="bi-translate">
                            <div class="row">
                                <div class="col-md-4"><x-select :name="$f('locale.language')" :label="__('Default language')" :options="['en' => 'English', 'sw' => 'Kiswahili']" :value="$s['locale.language']" /></div>
                                <div class="col-md-4"><x-select :name="$f('locale.timezone')" :label="__('Timezone')" :options="collect(timezone_identifiers_list())->filter(fn ($t) => str_starts_with($t, 'Africa/') || $t === 'UTC')->mapWithKeys(fn ($t) => [$t => $t])->all()" :value="$s['locale.timezone']" searchable /></div>
                                <div class="col-md-4"><x-select :name="$f('locale.date_format')" :label="__('Date format')" :options="['d/m/Y' => now()->format('d/m/Y'), 'd-m-Y' => now()->format('d-m-Y'), 'Y-m-d' => now()->format('Y-m-d'), 'd M Y' => now()->format('d M Y')]" :value="$s['locale.date_format']" /></div>
                            </div>
                        </x-card>
                        @break

                    @case('prefixes')
                        <x-card :title="__('Document number prefixes')" icon="bi-hash" :subtitle="__('Numbers are sequential per branch, e.g. INV-DSM01-000123.')">
                            <div class="row">
                                @foreach (collect($s)->filter(fn ($v, $k) => str_starts_with($k, 'prefix.')) as $key => $value)
                                    <div class="col-sm-6 col-md-4"><x-input :name="$f($key)" :label="\Illuminate\Support\Str::headline(substr($key, 7))" :value="$value" maxlength="6" required /></div>
                                @endforeach
                            </div>
                        </x-card>
                        @break
                @endswitch

                <x-form-actions :cancel="route('dashboard')" :label="__('Save settings')" />
            </form>
            @if ($group === 'features')
                @foreach ($presets as $key => $preset)
                    <form method="POST" action="{{ route('settings.preset') }}" id="preset-{{ $key }}" class="d-none"
                          onsubmit="return confirm(@js(__('Apply the :type setup? Module switches below will change.', ['type' => __($preset['label'])])))">
                        @csrf <input type="hidden" name="preset" value="{{ $key }}">
                    </form>
                @endforeach
            @endif
        </div>
    </div>
</x-layouts.app>
