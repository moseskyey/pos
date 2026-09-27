<?php

namespace App\Http\Requests\Admin;

class ExtendTenantRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
