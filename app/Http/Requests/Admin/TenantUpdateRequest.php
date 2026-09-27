<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

class TenantUpdateRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_email' => $this->filled('owner_email') ? strtolower(trim((string) $this->input('owner_email'))) : null,
            'owner_phone' => $this->filled('owner_phone') ? (PhoneNumber::normalize($this->input('owner_phone')) ?? $this->input('owner_phone')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')],
            'trial_ends_at' => ['nullable', 'date'],
            'paid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
