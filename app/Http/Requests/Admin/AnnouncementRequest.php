<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Announcement;
use App\Models\Platform\Tenant;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'tenant_id' => $this->input('tenant_id') ?: null]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:1000'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'tenant_id' => ['nullable', 'integer', Rule::exists(Tenant::class, 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }
}
