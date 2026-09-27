<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('adjustment'));
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:255']];
    }
}
