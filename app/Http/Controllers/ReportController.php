<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use App\Jobs\GenerateReportExport;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('reports.view');

        return view('reports.index', ['groups' => ReportRegistry::forUser($request->user())]);
    }

    public function show(Request $request, string $key, BranchContext $context): View
    {
        $report = ReportRegistry::find($key);
        abort_unless($report, 404);
        abort_unless($report->authorize($request->user()), 403);

        $filters = ReportFilters::fromRequest($request, $context->activeIds(), $report->defaultPreset());
        $result = $report->run($filters);

        return view('reports.show', compact('report', 'filters', 'result'));
    }

    public function export(ReportExportRequest $request, string $key, BranchContext $context): RedirectResponse
    {
        $this->authorize('reports.export');
        $report = ReportRegistry::find($key);
        abort_unless($report && $report->authorize($request->user()), 403);
        $filters = ReportFilters::fromRequest($request, $context->activeIds(), $report->defaultPreset());
        GenerateReportExport::dispatch($request->user()->id, $key, $filters->toArray(), $request->input('format'));

        return back()->with('success', __('Your export is being prepared. You will get a notification with the download link.'));
    }
}
