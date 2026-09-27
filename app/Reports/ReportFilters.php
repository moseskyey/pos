<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Normalised report filters: date range (presets or custom), branch ids and extras.
 */
class ReportFilters
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public array $branchIds,
        public array $params = [],
        public string $preset = 'month',
    ) {}

    public static function range(string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = now();

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default => [
                static::date($from)?->startOfDay() ?? $now->copy()->startOfMonth(),
                static::date($to)?->endOfDay() ?? $now->copy()->endOfDay(),
            ],
        };
    }

    /** A Y-m-d style date from user input, or null when missing or invalid (never throws). */
    protected static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fromRequest(Request $request, array $branchIds, string $defaultPreset = 'month'): self
    {
        $scalar = fn ($v) => is_scalar($v) ? (string) $v : null;
        $preset = $scalar($request->query('preset')) ?? ($request->filled('from') ? 'custom' : $defaultPreset);
        [$from, $to] = static::range($preset, $scalar($request->query('from')), $scalar($request->query('to')));
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Other filters are plain values; ignore anything array-shaped from the query string.
        $extra = array_filter($request->except(['preset', 'from', 'to', 'page', 'format']), fn ($v) => is_scalar($v) || $v === null);

        return new self($from, $to, $branchIds, $extra, $preset);
    }

    public static function fromArray(array $data): self
    {
        return new self(Carbon::parse($data['from']), Carbon::parse($data['to']), $data['branch_ids'], $data['params'] ?? [], $data['preset'] ?? 'custom');
    }

    public function toArray(): array
    {
        return ['from' => $this->from->toDateTimeString(), 'to' => $this->to->toDateTimeString(), 'branch_ids' => $this->branchIds, 'params' => $this->params, 'preset' => $this->preset];
    }

    public function param(string $key, mixed $default = null): mixed
    {
        $value = $this->params[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function days(): int
    {
        return max(1, (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1);
    }

    /** Previous period of equal length (for trends). */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days), $this->to->copy()->subDays($days), $this->branchIds, $this->params, 'custom');
    }

    public function label(): string
    {
        return $this->from->isSameDay($this->to) ? format_date($this->from) : format_date($this->from).' – '.format_date($this->to);
    }
}
