<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('branches.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }
}
