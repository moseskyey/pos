<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'periods' => ['required', 'integer', 'min:1', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'method' => ['required', Rule::in(['cash', 'bank', 'mobile', 'complimentary'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
