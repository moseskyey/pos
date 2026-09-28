<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('api.tokens');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'write' => ['boolean'],
            'expires_in_days' => ['nullable', 'integer', Rule::in([30, 90, 365])],
        ];
    }
}
