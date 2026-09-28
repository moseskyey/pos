<?php

namespace App\Http\Requests;

use App\Support\Features;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyFeaturePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return ['preset' => ['required', 'string', Rule::in(array_keys(Features::presets()))]];
    }
}
