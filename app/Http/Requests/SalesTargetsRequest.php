<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('targets.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($rows) => collect((array) $rows)->map(fn ($v) => is_string($v) ? (str_replace([',', ' '], '', $v) ?: null) : $v)->all();
        $this->merge(['targets' => $clean($this->input('targets')), 'rates' => $clean($this->input('rates'))]);
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'targets' => ['array'],
            'targets.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'rates' => ['array'],
            'rates.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
