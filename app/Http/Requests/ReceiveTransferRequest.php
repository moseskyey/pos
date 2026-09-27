<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('transfer'));
    }

    public function rules(): array
    {
        return ['quantities' => ['array'], 'quantities.*' => ['numeric', 'min:0'], 'notes' => ['array'], 'notes.*' => ['nullable', 'string', 'max:255']];
    }
}
