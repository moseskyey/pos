<?php

namespace App\Http\Requests;

use App\Models\StockTake;
use Illuminate\Foundation\Http\FormRequest;

class StartStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockTake::class);
    }

    public function rules(): array
    {
        return ['category_id' => ['nullable', 'exists:categories,id'], 'note' => ['nullable', 'string', 'max:500']];
    }
}
