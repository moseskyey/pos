<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dispatch', $this->route('transfer'));
    }

    public function rules(): array
    {
        return ['quantities' => ['array'], 'quantities.*' => ['numeric', 'min:0']];
    }
}
