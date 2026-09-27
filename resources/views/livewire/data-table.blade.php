<div class="card">
    {{-- Filter bar --}}
    <div class="filter-bar">
        @if ($hasSearch)
            <div class="search-input">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
        @endif

        @foreach ($filterDefinitions as $filter)
            @if ($filter->type === 'select')
                <select class="form-select w-auto" wire:model.live="filters.{{ $filter->key }}" aria-label="{{ __($filter->label) }}">
                    <option value="">{{ __('All') }} {{ \Illuminate\Support\Str::lower(__($filter->label)) }}</option>
                    @foreach ($filter->options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @elseif ($filter->type === 'boolean')
                <select class="form-select w-auto" wire:model.live="filters.{{ $filter->key }}" aria-label="{{ __($filter->label) }}">
                    <option value="">{{ __($filter->label) }}: {{ __('All') }}</option>
                    <option value="1">{{ __('Yes') }}</option>
                    <option value="0">{{ __('No') }}</option>
                </select>
            @elseif ($filter->type === 'daterange')
                <div class="d-flex align-items-center gap-1">
                    <input type="date" class="form-control" style="max-width:160px" wire:model.live="filters.{{ $filter->key }}.from" aria-label="{{ __('From') }}">
                    <span class="text-body-secondary">–</span>
                    <input type="date" class="form-control" style="max-width:160px" wire:model.live="filters.{{ $filter->key }}.to" aria-label="{{ __('To') }}">
                </div>
            @endif
        @endforeach

        @if ($filtersActive)
            <button type="button" class="btn btn-link btn-sm text-decoration-none" wire:click="resetFilters"><i class="bi bi-x-circle"></i> {{ __('Reset') }}</button>
        @endif

        <div class="ms-auto d-flex gap-2 align-items-center">
            <div wire:loading.delay class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">{{ __('Loading…') }}</span></div>

            @if ($isSelectable && count($selected) && $bulkActions)
                <div class="dropdown">
                    <button class="btn btn-soft-primary dropdown-toggle" data-bs-toggle="dropdown">{{ count($selected) }} {{ __('selected') }}</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach ($bulkActions as $method => $label)
                            <li><button class="dropdown-item" wire:click="runBulkAction('{{ $method }}')" wire:confirm="{{ __('Apply to selected records?') }}">{{ $label }}</button></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($isExportable)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary" data-bs-toggle="dropdown" aria-label="{{ __('Export') }}">
                        <i class="bi bi-download"></i><span class="d-none d-md-inline">{{ __('Export') }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" wire:click="export('xlsx')"><i class="bi bi-file-earmark-spreadsheet text-success"></i> Excel (XLSX)</button></li>
                        <li><button class="dropdown-item" wire:click="export('pdf')"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</button></li>
                    </ul>
                </div>
            @endif

            <select class="form-select w-auto" wire:model.live="perPage" aria-label="{{ __('Rows per page') }}">
                @foreach ([10, 15, 25, 50, 100] as $n)
                    <option value="{{ $n }}">{{ $n }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="table-responsive" wire:loading.class="opacity-50" style="max-height: 70vh">
        @if ($rows->count())
            <table class="table table-hover table-sticky table-stack align-middle">
                <thead>
                <tr>
                    @if ($isSelectable)
                        <th style="width:40px" data-label="">
                            <input type="checkbox" class="form-check-input" aria-label="{{ __('Select all') }}"
                                   wire:click="toggleSelectAll(@js($rows->pluck($rows->first()->getKeyName())->map(fn ($id) => (string) $id)->all()))"
                                   @checked($rows->pluck($rows->first()->getKeyName())->map(fn ($id) => (string) $id)->diff($selected)->isEmpty())>
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th class="{{ $column->headerClass }} {{ $column->sortable ? 'sortable' : '' }}"
                            @if ($column->sortable) wire:click="sortBy('{{ $column->sortKey }}')" role="button" tabindex="0" @keydown.enter="$wire.sortBy('{{ $column->sortKey }}')"
                            aria-sort="{{ $activeSort === $column->sortKey ? ($activeDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
                            {{ $column->label }}
                            @if ($column->sortable)
                                @if ($activeSort === $column->sortKey)
                                    <i class="bi bi-arrow-{{ $activeDirection === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @else
                                    <i class="bi bi-arrow-down-up opacity-25"></i>
                                @endif
                            @endif
                        </th>
                    @endforeach
                    <th class="text-end" data-label=""><span class="visually-hidden">{{ __('Actions') }}</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php $url = $table->renderRowUrl($row); @endphp
                    <tr wire:key="row-{{ $row->getKey() }}">
                        @if ($isSelectable)
                            <td data-label="">
                                <input type="checkbox" class="form-check-input" value="{{ $row->getKey() }}" wire:model.live="selected" aria-label="{{ __('Select row') }}">
                            </td>
                        @endif
                        @foreach ($columns as $i => $column)
                            <td class="{{ $column->class }}" data-label="{{ $column->label }}">
                                @if ($i === 0 && $url)
                                    <a href="{{ $url }}" class="fw-semibold text-decoration-none">{!! $column->render($row) !!}</a>
                                @else
                                    {!! $column->render($row) !!}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap" data-label="">{!! $table->renderActions($row) !!}</td>
                    </tr>
                @endforeach
                </tbody>
                @if ($totals)
                    <tfoot>
                    <tr>
                        @if ($isSelectable)<td data-label=""></td>@endif
                        @foreach ($columns as $i => $column)
                            <td class="{{ $column->class }}" data-label="{{ $i === 0 ? '' : $column->label }}">
                                {{ $i === 0 && ! isset($totals[0]) ? __('Total') : ($totals[$i] ?? '') }}
                            </td>
                        @endforeach
                        <td data-label=""></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        @else
            <x-empty-state :icon="$empty['icon'] ?? 'bi-inbox'" :title="$empty['title'] ?? null" :message="$empty['message'] ?? null"
                           :action="$filtersActive ? null : ($empty['action'] ?? null)" :action-label="$empty['actionLabel'] ?? null">
                @if ($filtersActive)
                    <button type="button" class="btn btn-outline-primary" wire:click="resetFilters">{{ __('Clear filters') }}</button>
                @endif
            </x-empty-state>
        @endif
    </div>

    @if ($rows->hasPages() || $rows->total() > 0)
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary">
            <span>{{ __('Showing :from–:to of :total', ['from' => $rows->firstItem() ?? 0, 'to' => $rows->lastItem() ?? 0, 'total' => number_format($rows->total())]) }}</span>
            <div>{{ $rows->onEachSide(1)->links() }}</div>
        </div>
    @endif

    @if ($formView)
        @include($formView)
    @endif
</div>
