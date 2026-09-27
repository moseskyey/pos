<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cashMovement', $this->route('shift'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
