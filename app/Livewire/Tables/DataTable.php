<?php

namespace App\Livewire\Tables;

use App\Exports\ArrayExport;
use App\Support\PdfExporter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Base Livewire data table: search, sortable columns, filters, per-page,
 * pagination, bulk select, XLSX/PDF export and stacked cards on mobile.
 */
abstract class DataTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: '')]
    public string $sortDirection = '';

    public int $perPage = 15;

    #[Url(except: [])]
    public array $filters = [];

    public array $selected = [];

    /** Default sort when none selected. */
    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    protected bool $exportable = true;

    protected bool $selectable = false;

    /** Optional Blade view rendered inside the component (e.g. a modal form). */
    protected ?string $formView = null;

    abstract protected function query(): Builder;

    /** @return array<int, Column> */
    abstract protected function columns(): array;

    /** @return array<int, Filter> */
    protected function filterDefinitions(): array
    {
        return [];
    }

    /** Columns searched with LIKE. Supports "relation.column". */
    protected function searchable(): array
    {
        return [];
    }

    /** @return array<string, string> method => label */
    protected function bulkActions(): array
    {
        return [];
    }

    protected function rowActions(mixed $row): ?string
    {
        return null;
    }

    protected function rowUrl(mixed $row): ?string
    {
        return null;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-inbox', 'title' => __('No records found'), 'message' => __('Try changing your search or filters.')];
    }

    protected function title(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public function mount(): void
    {
        foreach ($this->filterDefinitions() as $filter) {
            if (! array_key_exists($filter->key, $this->filters)) {
                $this->filters[$filter->key] = $filter->type === 'daterange' ? ['from' => null, 'to' => null] : $filter->default;
            }
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = collect($this->columns())->filter->sortable->pluck('sortKey')->all();
        if (! in_array($field, $allowed, true)) {
            return;
        }
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->mount();
        $this->resetPage();
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->query();

        if (($term = trim($this->search)) !== '' && $this->searchable()) {
            $query->where(function (Builder $q) use ($term) {
                foreach ($this->searchable() as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $col] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn ($r) => $r->where($col, 'like', "%{$term}%"));
                    } else {
                        $q->orWhere($q->getModel()->qualifyColumn($column), 'like', "%{$term}%");
                    }
                }
            });
        }

        foreach ($this->filterDefinitions() as $filter) {
            $filter->apply($query, $this->filters[$filter->key] ?? null);
        }

        return $query;
    }

    protected function sortedQuery(): Builder
    {
        $query = $this->filteredQuery();
        $field = $this->sortField ?: $this->defaultSort;
        $direction = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : $this->defaultDirection;

        if ($field) {
            $query->orderBy(str_contains($field, '.') || str_contains($field, '(') ? $field : $query->getModel()->qualifyColumn($field), $direction);
        }

        return $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    protected function rows(): LengthAwarePaginator
    {
        return $this->sortedQuery()->paginate(in_array($this->perPage, [10, 15, 25, 50, 100], true) ? $this->perPage : 15);
    }

    protected function totals(): array
    {
        $totals = [];
        foreach ($this->columns() as $i => $column) {
            if ($column->total && $column->field) {
                $sum = (clone $this->filteredQuery())->reorder()->sum($column->field);
                $totals[$i] = $column->total === 'money' ? money($sum) : qty($sum);
            }
        }

        return $totals;
    }

    public function toggleSelectAll(array $ids): void
    {
        $ids = array_map('strval', $ids);
        $this->selected = count(array_diff($ids, $this->selected)) === 0
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function runBulkAction(string $action): void
    {
        if (! array_key_exists($action, $this->bulkActions()) || ! $this->selected) {
            return;
        }
        $count = $this->{$action}($this->selected);
        $this->selected = [];
        $this->dispatch('toast', message: __(':count records updated.', ['count' => $count ?? 0]), type: 'success');
    }

    public function export(string $format = 'xlsx')
    {
        abort_unless($this->exportable, 403);

        $columns = collect($this->columns())->filter(fn (Column $c) => $c->exportable && $c->visible)->values();
        $headings = $columns->map(fn (Column $c) => $c->label)->all();
        $rows = [];
        $this->sortedQuery()->limit(10000)->get()->each(function ($row) use ($columns, &$rows) {
            $rows[] = $columns->map(fn (Column $c) => $this->plain($c->exportValue($row)))->all();
        });

        $title = $this->title();
        $this->logExport($title, $format, count($rows));

        if ($format === 'pdf') {
            return PdfExporter::table($title, $headings, $rows, null, ['Generated' => now()->format('d/m/Y H:i')]);
        }

        return Excel::download(new ArrayExport($headings, $rows, $title), Str::slug($title).'-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function logExport(string $title, string $format, int $rows): void
    {
        activity('exports')->withProperties(['table' => $title, 'format' => $format, 'rows' => $rows])->log("Exported $title");
    }

    protected function plain(mixed $value): mixed
    {
        if ($value instanceof HtmlString) {
            $value = (string) $value;
        }
        if ($value instanceof \BackedEnum) {
            $value = method_exists($value, 'label') ? $value->label() : $value->value;
        }

        return is_string($value) ? html_entity_decode(trim(strip_tags($value))) : $value;
    }

    public function render()
    {
        $rows = $this->rows();

        return view('livewire.data-table', [
            'rows' => $rows,
            'columns' => collect($this->columns())->filter(fn (Column $c) => $c->visible && ! $c->onlyExport)->values(),
            'filterDefinitions' => $this->filterDefinitions(),
            'bulkActions' => $this->bulkActions(),
            'hasSearch' => (bool) $this->searchable(),
            'empty' => $this->emptyState(),
            'totals' => $this->totals(),
            'isExportable' => $this->exportable,
            'isSelectable' => $this->selectable || (bool) $this->bulkActions(),
            'table' => $this,
            'formView' => $this->formView,
            'activeSort' => $this->sortField ?: $this->defaultSort,
            'activeDirection' => $this->sortDirection ?: $this->defaultDirection,
            'filtersActive' => trim($this->search) !== '' || collect($this->filters)->flatten()->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
        ]);
    }

    /** Exposed to the view. */
    public function renderActions(mixed $row): ?string
    {
        return $this->rowActions($row);
    }

    public function renderRowUrl(mixed $row): ?string
    {
        return $this->rowUrl($row);
    }
}
