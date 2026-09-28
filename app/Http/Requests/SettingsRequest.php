<?php

namespace App\Http\Requests;

use App\Support\Features;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return match ($this->route('group')) {
            'business' => [
                'business_name' => ['required', 'string', 'max:120'],
                'business_tin' => ['nullable', 'string', 'max:30'],
                'business_vrn' => ['nullable', 'string', 'max:30'],
                'business_address' => ['nullable', 'string', 'max:255'],
                'business_phone' => ['nullable', 'string', 'max:30'],
                'business_email' => ['nullable', 'email', 'max:120'],
                'logo' => ['nullable', 'image', 'max:2048'],
            ],
            'currency' => [
                'currency_symbol' => ['required', 'string', 'max:6'],
                'currency_decimals' => ['required', 'integer', 'between:0,2'],
                'currency_thousands_separator' => ['nullable', 'string', 'max:1'],
                'currency_usd_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
                'tax_vat_rate' => ['required', 'numeric', 'between:0,100'],
                'tax_prices_include_vat' => ['boolean'],
            ],
            'receipt' => [
                'receipt_paper' => ['required', Rule::in(['58mm', '80mm'])],
                'receipt_header' => ['nullable', 'string', 'max:500'],
                'receipt_footer' => ['nullable', 'string', 'max:500'],
                'receipt_show_logo' => ['boolean'],
                'receipt_auto_print' => ['boolean'],
                'receipt_show_qr' => ['boolean'],
                'receipt_print_mode' => ['required', Rule::in(['browser', 'escpos'])],
                'receipt_drawer_kick' => ['boolean'],
            ],
            'features' => collect(Features::all())->keys()
                ->mapWithKeys(fn ($key) => [str_replace('.', '_', Features::settingKey($key)) => ['boolean']])->all(),
            'pos' => [
                'pos_negative_stock' => ['required', Rule::in(['block', 'warn', 'allow'])],
                'pos_max_discount_percent' => ['required', 'numeric', 'between:0,100'],
                'pos_below_cost' => ['required', Rule::in(['block', 'approval', 'allow'])],
                'pos_rounding' => ['required', Rule::in(['0', '50', '100'])],
                'pos_scan_sound' => ['boolean'],
                'pos_lock_minutes' => ['required', 'integer', 'between:0,240'],
                'pos_default_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'loyalty_enabled' => ['boolean'],
                'loyalty_earn_per_amount' => ['required', 'numeric', 'min:1'],
                'loyalty_point_value' => ['required', 'numeric', 'min:0'],
                'inventory_costing' => ['required', Rule::in(['average', 'last'])],
                'inventory_expiry_alert_days' => ['required', 'integer', 'between:1,365'],
                'credit_default_days' => ['sometimes', 'integer', 'between:0,365'],
            ],
            'payments' => [
                'payments_cash' => ['boolean'], 'payments_cash_usd' => ['boolean'], 'payments_mpesa' => ['boolean'], 'payments_tigopesa' => ['boolean'],
                'payments_airtel' => ['boolean'], 'payments_halopesa' => ['boolean'], 'payments_card' => ['boolean'],
                'payments_bank' => ['boolean'], 'payments_credit' => ['boolean'], 'payments_store_credit' => ['boolean'],
                'payments_gateway' => ['required', Rule::in(['manual', 'fastlipa'])],
                'payments_fastlipa_api_key' => ['nullable', 'string', 'max:255'],
                'payments_fastlipa_base_url' => ['nullable', 'url', 'max:255'],
                'payments_fastlipa_webhook_secret' => ['nullable', 'string', 'max:255'],
            ],
            'notifications' => [
                'sms_driver' => ['required', Rule::in(['log', 'beem'])],
                'sms_sender_id' => ['nullable', 'string', 'max:11'],
                'sms_api_key' => ['nullable', 'string', 'max:255'],
                'sms_api_secret' => ['nullable', 'string', 'max:255'],
                'notify_low_stock_email' => ['boolean'],
                'notify_low_stock_sms' => ['boolean'],
                'notify_over_short_threshold' => ['required', 'numeric', 'min:0'],
                'fiscal_driver' => ['required', Rule::in(['null'])],
            ],
            'localisation' => [
                'locale_language' => ['required', Rule::in(['en', 'sw'])],
                'locale_timezone' => ['required', 'timezone'],
                'locale_date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'd-m-Y', 'd M Y'])],
            ],
            'prefixes' => collect(config('dukapos.settings'))
                ->keys()->filter(fn ($k) => str_starts_with($k, 'prefix.'))
                ->mapWithKeys(fn ($k) => [str_replace('.', '_', $k) => ['required', 'alpha_num', 'max:6']])->all(),
            default => [],
        };
    }
}
