<?php

namespace App\Http\Requests\Admin;

class SuspendTenantRequest extends AdminRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
