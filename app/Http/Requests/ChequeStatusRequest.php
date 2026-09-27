<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChequeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cheque'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['cleared', 'bounced', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
