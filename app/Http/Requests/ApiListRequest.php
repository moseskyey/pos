<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Query filters shared by /api/v1 list endpoints. */
class ApiListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // each endpoint checks the user's permission
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'updated_since' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
