<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('backups.manage');
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:db,full']];
    }
}
