<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailSaleDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('document', $this->route('sale'));
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
