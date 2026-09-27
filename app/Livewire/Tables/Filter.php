<?php

namespace App\Livewire\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class Filter
{
    public string $type = 'select';

    public array $options = [];

    public ?Closure $callback = null;

    public mixed $default = null;

    public function __construct(public string $key, public string $label) {}

    public static function select(string $key, string $label, array $options): static
    {
        $filter = new static($key, $label);
        $filter->options = $options;

        return $filter;
    }

    public static function dateRange(string $key, string $label = 'Date'): static
    {
        $filter = new static($key, $label);
        $filter->type = 'daterange';

        return $filter;
    }

    public static function boolean(string $key, string $label): static
    {
        $filter = new static($key, $label);
        $filter->type = 'boolean';

        return $filter;
    }

    public function query(Closure $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function apply(Builder $query, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }
        if ($this->type === 'daterange') {
            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;
            if (! $from && ! $to) {
                return;
            }
        }

        if ($this->callback) {
            ($this->callback)($query, $value);

            return;
        }

        match ($this->type) {
            'daterange' => $query
                ->when($value['from'] ?? null, fn ($q, $from) => $q->where($query->getModel()->qualifyColumn($this->key), '>=', $from.' 00:00:00'))
                ->when($value['to'] ?? null, fn ($q, $to) => $q->where($query->getModel()->qualifyColumn($this->key), '<=', $to.' 23:59:59')),
            'boolean' => $query->where($query->getModel()->qualifyColumn($this->key), (bool) $value),
            default => $query->where($query->getModel()->qualifyColumn($this->key), $value),
        };
    }
}
