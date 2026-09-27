<x-layouts.app :title="__('Targets & commission')" :breadcrumbs="[__('Reports'), __('Targets & commission')]">
    <x-page-header :title="__('Targets & commission')" :subtitle="$branch ? __(':b · :m', ['b' => $branch->name, 'm' => \Illuminate\Support\Carbon::parse($month.'-01')->translatedFormat('F Y')]) : null">
        <form method="GET" action="{{ route('targets.index') }}" class="d-flex gap-2">
            <input type="month" name="month" value="{{ $month }}" class="form-control" aria-label="{{ __('Month') }}" onchange="this.form.submit()">
        </form>
        @if (\Illuminate\Support\Facades\Route::has('reports.show'))
            <a href="{{ route('reports.show', ['key' => 'commission', 'from' => $month.'-01', 'to' => \Illuminate\Support\Carbon::parse($month.'-01')->endOfMonth()->toDateString()]) }}" class="btn btn-outline-secondary"><i class="bi bi-bar-chart-line"></i> {{ __('Commission report') }}</a>
        @endif
    </x-page-header>

    @if (! $branch)
        <x-empty-state icon="bi-shop" :title="__('Select a single branch in the navbar first.')" />
    @else
        <form method="POST" action="{{ route('targets.update') }}" x-data="dirtyForm">
            @csrf @method('PUT')
            <input type="hidden" name="month" value="{{ $month }}">
            <x-card flush>
                <div class="table-responsive">
                    <table class="table table-stack align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('Salesperson') }}</th><th style="width:170px">{{ __('Commission rate') }}</th><th style="width:200px">{{ __('Monthly target') }}</th>
                            <th class="text-end">{{ __('Net sales so far') }}</th><th style="width:220px">{{ __('Progress') }}</th><th class="text-end">{{ __('Commission') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse ($people as $person)
                            @php $p = $progress->get($person->id); $target = $targets->get($person->id)?->amount; $pct = $target && $target > 0 ? min(100, (float) ($p['commissionable'] ?? 0) / (float) $target * 100) : null; @endphp
                            <tr>
                                <td data-label="{{ __('Salesperson') }}"><div class="d-flex align-items-center gap-2"><x-avatar :user="$person" size="sm" /> <span class="fw-semibold">{{ $person->name }}</span></div></td>
                                <td data-label="{{ __('Commission rate') }}"><x-input :name="'rates['.$person->id.']'" type="number" step="0.01" min="0" max="100" :value="$person->commission_rate !== null ? (float) $person->commission_rate : null" suffix="%" class="mb-0" :aria-label="__('Commission rate for :n', ['n' => $person->name])" /></td>
                                <td data-label="{{ __('Monthly target') }}"><x-input :name="'targets['.$person->id.']'" type="number" step="1000" min="0" :value="$target !== null ? (float) $target : null" prefix="TSh" class="mb-0" :aria-label="__('Target for :n', ['n' => $person->name])" /></td>
                                <td data-label="{{ __('Net sales so far') }}" class="text-end text-money">{{ money($p['commissionable'] ?? 0) }}</td>
                                <td data-label="{{ __('Progress') }}">
                                    @if ($pct !== null)
                                        <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-valuenow="{{ (int) $pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar {{ $pct >= 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div><span class="small">{{ number_format($pct) }}%</span></div>
                                    @else <span class="text-body-secondary small">{{ __('No target') }}</span> @endif
                                </td>
                                <td data-label="{{ __('Commission') }}" class="text-end text-money fw-semibold">{{ money($p['commission'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="bi-people" :title="__('No salespeople at this branch')" /></td></tr>
                        @endforelse
                        <tr class="table-light">
                            <td class="fw-semibold">{{ __('Whole branch') }}</td><td></td>
                            <td><x-input name="targets[branch]" type="number" step="1000" min="0" :value="$targets->get('branch')?->amount !== null ? (float) $targets->get('branch')->amount : null" prefix="TSh" class="mb-0" :aria-label="__('Branch target')" /></td>
                            <td class="text-end text-money fw-semibold">{{ money(\App\Support\Money::sum($progress, 'commissionable')) }}</td><td></td>
                            <td class="text-end text-money fw-semibold">{{ money(\App\Support\Money::sum($progress, 'commission')) }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>
            <x-form-actions :cancel="route('dashboard')" :label="__('Save targets')" />
        </form>
    @endif
</x-layouts.app>
