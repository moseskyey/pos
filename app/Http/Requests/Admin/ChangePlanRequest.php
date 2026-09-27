<?php

namespace App\Http\Requests\Admin;

use App\Models\Platform\Plan;
use Illuminate\Validation\Rule;

class ChangePlanRequest extends AdminRequest
{
    public function rules(): array
    {
        return ['plan_id' => ['nullable', 'integer', Rule::exists(Plan::class, 'id')]];
    }
}
