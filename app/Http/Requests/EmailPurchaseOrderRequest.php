<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchases.manage');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
