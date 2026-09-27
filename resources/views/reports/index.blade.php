<x-layouts.app :title="__('Reports')" :breadcrumbs="[__('Reports')]">
    <x-page-header :title="__('Reports')" :subtitle="__('Filter, chart, print and export to Excel or PDF.')" />
    @foreach ($groups as $group => $reports)
        <h6 class="text-uppercase text-body-secondary small fw-semibold mt-4 mb-3" style="letter-spacing:.06em">{{ $group }}</h6>
        <div class="row g-3">
            @foreach ($reports as $key => $report)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('reports.show', $key) }}" class="card h-100 text-decoration-none text-reset hover-lift">
                        <div class="card-body d-flex gap-3">
                            <div class="stat-icon bg-primary-soft text-primary rounded-3 d-grid flex-shrink-0" style="width:46px;height:46px;place-items:center;font-size:1.25rem"><i class="bi {{ $report->icon() }}"></i></div>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $report->title() }}</div>
                                <div class="small text-body-secondary">{{ $report->description() }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</x-layouts.app>
