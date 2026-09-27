<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\DataTable;
use App\Models\Platform\AdminActivity;

/**
 * Data tables in the platform admin area (admins only). They read central models only.
 */
abstract class AdminDataTable extends DataTable
{
    public function boot(): void
    {
        abort_unless(auth('admin')->user()?->is_active, 403);
    }

    protected function logExport(string $title, string $format, int $rows): void
    {
        AdminActivity::record('export', "Exported {$title}", null, ['format' => $format, 'rows' => $rows]);
    }
}
