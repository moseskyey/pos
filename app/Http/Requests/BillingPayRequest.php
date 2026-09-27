<?php

namespace App\Http\Requests;

use App\Models\Platform\Plan;
use App\Services\Platform\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'periods' => ['required', 'integer', 'min:1', 'max:'.SubscriptionService::MAX_PERIODS],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
