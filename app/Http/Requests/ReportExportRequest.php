<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.export');
    }

    public function rules(): array
    {
        return ['format' => ['required', Rule::in(['xlsx', 'pdf'])]];
    }
}
