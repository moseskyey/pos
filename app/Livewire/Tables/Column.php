<?php

namespace App\Livewire\Tables;

use Closure;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Column
{
    public ?string $sortKey = null;

    public bool $sortable = false;

    public ?Closure $formatter = null;

    public bool $raw = false;

    public string $class = '';

    public string $headerClass = '';

    public bool $exportable = true;

    public bool $onlyExport = false;

    public ?Closure $exportFormatter = null;

    public bool $visible = true;

    public ?string $total = null;

    public function __construct(public string $label, public ?string $field = null) {}

    public static function make(string $label, ?string $field = null): static
    {
        return new static($label, $field);
    }

    public function sortable(?string $key = null): static
    {
        $this->sortable = true;
        $this->sortKey = $key ?? $this->field;

        return $this;
    }

    /** Plain-text formatter (escaped). */
    public function format(Closure $callback): static
    {
        $this->formatter = $callback;

        return $this;
    }

    /** HTML formatter (not escaped – callback must escape user data). */
    public function html(Closure $callback): static
    {
        $this->formatter = $callback;
        $this->raw = true;

        return $this;
    }

    /** Render a Blade view with ['row' => $row]. */
    public function view(string $view): static
    {
        return $this->html(fn ($row) => view($view, ['row' => $row])->render());
    }

    public function money(): static
    {
        $this->class .= ' text-end text-money';
        $this->headerClass .= ' text-end';
        $this->formatter ??= fn ($row) => money(data_get($row, $this->field));
        $this->exportFormatter ??= fn ($row) => (float) data_get($row, $this->field);

        return $this;
    }

    public function number(): static
    {
        $this->class .= ' text-end';
        $this->headerClass .= ' text-end';
        $this->formatter ??= fn ($row) => qty(data_get($row, $this->field));
        $this->exportFormatter ??= fn ($row) => (float) data_get($row, $this->field);

        return $this;
    }

    public function date(bool $withTime = false): static
    {
        $this->class .= ' text-nowrap';
        $this->formatter ??= fn ($row) => format_date(data_get($row, $this->field), $withTime);

        return $this;
    }

    public function badge(): static
    {
        return $this->html(fn ($row) => Blade::render('<x-status-badge :status="$status" />', ['status' => data_get($row, $this->field)]))
            ->exportAs(function ($row) {
                $value = data_get($row, $this->field);

                return $value instanceof \BackedEnum ? (method_exists($value, 'label') ? $value->label() : $value->value) : Str::headline((string) $value);
            });
    }

    public function boolean(): static
    {
        return $this->html(fn ($row) => data_get($row, $this->field)
            ? '<span class="badge rounded-pill text-bg-success-soft status-badge">'.e(__('Active')).'</span>'
            : '<span class="badge rounded-pill text-bg-secondary-soft status-badge">'.e(__('Inactive')).'</span>')
            ->exportAs(fn ($row) => data_get($row, $this->field) ? __('Yes') : __('No'));
    }

    public function align(string $align): static
    {
        $this->class .= ' text-'.$align;
        $this->headerClass .= ' text-'.$align;

        return $this;
    }

    public function classes(string $class): static
    {
        $this->class .= ' '.$class;

        return $this;
    }

    public function exportAs(Closure $callback): static
    {
        $this->exportFormatter = $callback;

        return $this;
    }

    public function hideOnExport(): static
    {
        $this->exportable = false;

        return $this;
    }

    public function exportOnly(): static
    {
        $this->onlyExport = true;

        return $this;
    }

    public function visible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /** Footer total: 'money' | 'number'. Computed from the filtered query column. */
    public function total(string $type = 'money'): static
    {
        $this->total = $type;

        return $this;
    }

    public function render(mixed $row): HtmlString|string
    {
        $value = $this->formatter ? ($this->formatter)($row) : data_get($row, $this->field);

        if ($value instanceof HtmlString) {
            return $value;
        }

        return $this->raw ? new HtmlString((string) $value) : e((string) ($value ?? '—'));
    }

    public function exportValue(mixed $row): mixed
    {
        if ($this->exportFormatter) {
            return ($this->exportFormatter)($row);
        }
        if ($this->formatter && ! $this->raw) {
            return ($this->formatter)($row);
        }
        $value = data_get($row, $this->field);
        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : $value->value;
        }

        return $this->raw && $this->formatter ? strip_tags((string) ($this->formatter)($row)) : $value;
    }
}
