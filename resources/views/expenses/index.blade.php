<x-layouts.app :title="__('Expenses')" :breadcrumbs="[__('Expenses')]">
    <x-page-header :title="__('Expenses')" :subtitle="__('Rent, LUKU, salaries, transport and other running costs.')">
        @can('expenses.manage')
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-tags"></i> {{ __('Categories') }}</button>
            <a href="{{ route('recurring-expenses.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> {{ __('Recurring') }}</a>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Record expense') }}</a>
        @endcan
    </x-page-header>
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-4"><x-stat-card :label="__('This month')" :value="money($monthTotal)" icon="bi-calendar-month" :trend="$lastMonth > 0 ? ($monthTotal - $lastMonth) / $lastMonth * 100 : null" :hint="__('vs last month')" /></div>
                <div class="col-md-4"><x-stat-card :label="__('Last month')" :value="money($lastMonth)" icon="bi-calendar2" color="secondary" /></div>
                <div class="col-md-4"><x-stat-card :label="__('Today')" :value="money($today)" icon="bi-calendar-day" color="warning" /></div>
            </div>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('This month by category')">
                @if (count($chart['labels']))<div class="chart-box-sm" style="height:180px"><canvas data-chart='@json($chart)'></canvas></div>@else<p class="text-body-secondary small mb-0">{{ __('No expenses this month.') }}</p>@endif
            </x-card>
        </div>
    </div>
    <livewire:tables.expenses-table />
    @push('modals')
        <x-modal id="catModal" :title="__('Expense categories')">
            <ul class="list-group mb-3">
                @foreach ($categories as $c)<li class="list-group-item d-flex justify-content-between">{{ $c->name }} <span class="badge text-bg-secondary-soft">{{ $c->expenses_count }}</span></li>@endforeach
            </ul>
            <form method="POST" action="{{ route('expense-categories.store') }}" class="d-flex gap-2">@csrf
                <input type="text" name="name" class="form-control" placeholder="{{ __('New category') }}" required>
                <button class="btn btn-primary">{{ __('Add') }}</button>
            </form>
        </x-modal>
    @endpush
</x-layouts.app>
