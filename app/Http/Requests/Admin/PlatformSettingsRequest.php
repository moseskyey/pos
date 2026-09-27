<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use Illuminate\Validation\Rule;

class PlatformSettingsRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->is_super;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'signups_enabled' => $this->boolean('signups_enabled'),
            'fastlipa_enabled' => $this->boolean('fastlipa_enabled'),
            'default_plan_id' => $this->input('default_plan_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'signups_enabled' => ['boolean'],
            'default_plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')],
            'reminder_days' => ['nullable', 'string', 'max:30', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_whatsapp' => ['nullable', 'string', 'max:30'],
            'billing_instructions' => ['nullable', 'string', 'max:1000'],
            'fastlipa_enabled' => ['boolean'],
            'fastlipa_api_key' => ['nullable', 'string', 'max:255'],
            'fastlipa_base_url' => ['required', 'url', 'max:255'],
            'fastlipa_webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
