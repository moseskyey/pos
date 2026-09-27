<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
        foreach (['max_branches', 'max_users', 'max_products'] as $limit) {
            if ($this->input($limit) === '') {
                $this->merge([$limit => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', Rule::unique(Plan::class, 'slug')->ignore($this->route('plan'))],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'interval_months' => ['required', 'integer', Rule::in([1, 3, 6, 12])],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_products' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
