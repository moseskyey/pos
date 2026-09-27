<?php

namespace App\Http\Requests\Admin;

class RefundPaymentRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->is_super;
    }

    public function rules(): array
    {
        return [
            'revoke' => ['nullable', 'boolean'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }
}
