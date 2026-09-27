@php
    use App\Reports\Report;
    $query = request()->query();
@endphp
<x-layouts.app :title="$report->title()" :breadcrumbs="[__('Reports') => route('reports.index'), $report->title()]">
    <x-page-header :title="$report->title()" :subtitle="($report->usesDates() ? $filters->label().' · ' : '').(current_branch()?->name ?? __('All branches'))">
        @can('reports.export')
            <form method="POST" action="{{ route('reports.export', ['key' => $report::key()] + $query) }}" class="d-flex gap-2">
                @csrf
                <button name="format" value="xlsx" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet text-success"></i> Excel</button>
                <button name="format" value="pdf" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</button>
            </form>
        @endcan
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
    </x-page-header>

    {{-- Filters --}}
    @if ($report->usesDates() || $report->filters())
        <div class="card mb-4 no-print">
            <form method="GET" class="card-body d-flex flex-wrap gap-3 align-items-end">
                @if ($report->usesDates())
                    <div>
                        <label class="form-label small">{{ __('Period') }}</label>
                        <x-date-range-picker :preset="$filters->preset" :from="$filters->from->toDateString()" :to="$filters->to->toDateString()" />
                    </div>
                @endif
                @foreach ($report->filters() as $key => $filter)
                    <div>
                        <label class="form-label small" for="flt-{{ $key }}">{{ $filter['label'] }}</label>
                        @if (isset($filter['options']))
                            <select name="{{ $key }}" id="flt-{{ $key }}" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                                @foreach ($filter['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected($filters->param($key, array_key_first($filter['options'])) == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="number" name="{{ $key }}" id="flt-{{ $key }}" class="form-control form-control-sm" style="width:110px" value="{{ $filters->param($key, $filter['default'] ?? null) }}">
                        @endif
                    </div>
                @endforeach
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ __('Apply') }}</button>
            </form>
        </div>
    @endif

    @if ($result->kpis)
        <div class="row g-3 mb-4">
            @foreach ($result->kpis as $kpi)
                <div class="col-sm-6 col-xl-3"><x-stat-card :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon'] ?? 'bi-graph-up'" :color="$kpi['color'] ?? 'primary'" :hint="$kpi['hint'] ?? null" /></div>
            @endforeach
        </div>
    @endif

    @if ($result->chart && count($result->chart['labels'] ?? []))
        <x-card class="mb-4">
            <div class="chart-box"><canvas data-chart='@json($result->chart)'></canvas></div>
        </x-card>
    @endif

    @if ($result->note)<div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ $result->note }}</div>@endif

    <div class="card">
        @if (! $result->rows)
            <x-empty-state icon="bi-bar-chart" :title="__('No data for this period')" :message="__('Try a different date range or branch.')" />
        @else
            <div class="table-responsive" style="max-height: 75vh">
                <table class="table table-hover table-sticky table-stack align-middle">
                    <thead><tr>
                        @foreach ($result->columns as $col)
                            <th class="{{ in_array($col['type'] ?? null, ['money', 'number', 'integer', 'percent']) ? 'text-end' : '' }}">{{ $col['label'] }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                    @foreach ($result->rows as $row)
                        <tr class="{{ ! empty($row['bold']) ? 'fw-bold bg-surface' : '' }}">
                            @foreach ($result->columns as $key => $col)
                                @php $type = $col['type'] ?? null; $v = $row[$key] ?? null; @endphp
                                <td data-label="{{ $col['label'] }}" class="{{ in_array($type, ['money', 'number', 'integer', 'percent']) ? 'text-end text-money' : '' }} {{ $type === 'money' && $v !== null && \App\Support\Money::isNegative($v) ? 'text-danger' : '' }}">
                                    {{ $v === null && $type !== 'percent' ? '' : Report::display($v, $type) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                    @if ($result->totals)
                        <tfoot><tr>
                            @foreach ($result->columns as $key => $col)
                                @php $type = $col['type'] ?? null; @endphp
                                <td data-label="{{ $col['label'] }}" class="{{ in_array($type, ['money', 'number', 'integer', 'percent']) ? 'text-end text-money' : '' }}">{{ array_key_exists($key, $result->totals) ? Report::display($result->totals[$key], $type) : '' }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
