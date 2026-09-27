<?php

namespace App\Http\Requests\Admin;

class PurgeTenantRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->is_super;
    }

    public function rules(): array
    {
        return ['confirm' => ['required', 'string', 'in:'.$this->route('tenant')->slug]];
    }

    public function messages(): array
    {
        return ['confirm.in' => __('Type the business ID exactly to confirm.')];
    }
}
