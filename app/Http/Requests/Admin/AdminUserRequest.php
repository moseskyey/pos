<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->is_super;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'is_super' => $this->boolean('is_super'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $admin = $this->route('admin');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique(PlatformAdmin::class, 'email')->ignore($admin)],
            'password' => [$admin ? 'nullable' : 'required', 'confirmed', Password::min(10)],
            'is_super' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
