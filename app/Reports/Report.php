<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Str;

abstract class Report
{
    abstract public static function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    abstract public function run(ReportFilters $filters): ReportResult;

    public function icon(): string
    {
        return 'bi-bar-chart';
    }

    public function group(): string
    {
        return __('Sales');
    }

    /** Extra filter controls: key => ['label' => ..., 'options' => [...]] or ['label' => ..., 'type' => 'number'] */
    public function filters(): array
    {
        return [];
    }

    public function usesDates(): bool
    {
        return true;
    }

    public function defaultPreset(): string
    {
        return 'month';
    }

    public function requiresProfit(): bool
    {
        return false;
    }

    public function authorize(User $user): bool
    {
        return $user->can('reports.view') && (! $this->requiresProfit() || $user->can('reports.profit.view'));
    }

    public function slug(): string
    {
        return Str::slug(static::key());
    }

    /** Format a raw cell for display. */
    public static function display(mixed $value, ?string $type): string
    {
        if (in_array($type, ['date', 'datetime'], true) && is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $value;
        }

        return match ($type) {
            'money' => money($value ?? 0),
            'number' => qty($value ?? 0),
            'integer' => number_format((int) $value),
            'percent' => $value === null ? '—' : number_format((float) $value, 1).'%',
            'date' => format_date($value),
            'datetime' => format_date($value, true),
            default => (string) ($value ?? '—'),
        };
    }
}
