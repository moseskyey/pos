<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Base for platform admin forms: any active admin (super-only routes add their own middleware). */
abstract class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->is_active;
    }
}
