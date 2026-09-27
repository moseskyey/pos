<?php

namespace App\Http\Requests;

use App\Models\ProductSerial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSerialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductSerial::class);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('track_serials', true)->whereNull('deleted_at')],
            'serials' => ['required', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return ['product_id.exists' => __('Choose a product that tracks serial numbers.')];
    }
}
