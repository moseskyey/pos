<?php

namespace App\Jobs;

use App\Exports\ArrayExport;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Builds a report file in the background and notifies the user when ready.
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $userId, public string $reportKey, public array $filters, public string $format) {}

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        Auth::setUser($user);
        $report = ReportRegistry::find($this->reportKey);
        abort_unless($report && $report->authorize($user), 403);

        $filters = ReportFilters::fromArray($this->filters);
        $result = $report->run($filters);
        $headings = array_map(fn ($c) => $c['label'], $result->columns);
        $keys = array_keys($result->columns);
        $rows = array_map(fn ($row) => array_map(fn ($k) => $this->cell($row[$k] ?? null, $result->columns[$k]['type'] ?? null), $keys), $result->rows);
        $totals = $result->totals ? array_map(fn ($k) => isset($result->totals[$k]) ? $this->cell($result->totals[$k], $result->columns[$k]['type'] ?? null) : '', $keys) : null;

        $name = Str::slug($report->title()).'-'.now()->format('Ymd-His').'.'.$this->format;
        $path = 'exports/'.$user->id.'/'.$name;
        if ($this->format === 'pdf') {
            $content = Pdf::loadView('pdf.table', ['title' => $report->title(), 'headings' => $headings, 'rows' => $rows, 'totals' => $totals,
                'meta' => [__('Period') => $report->usesDates() ? $filters->label() : format_date(now()), __('Generated') => now()->format('d/m/Y H:i')]])
                ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')->output();
        } else {
            $content = Excel::raw(new ArrayExport($headings, $totals ? [...$rows, $totals] : $rows, $report->title()), ExcelWriter::XLSX);
        }
        Storage::disk('local')->put($path, $content);
        activity('exports')->causedBy($user)->withProperties(['report' => $this->reportKey, 'format' => $this->format])->log('Report exported');

        $user->notify(new SystemAlert(
            __(':report is ready', ['report' => $report->title()]),
            __('Your :f export has finished. Click to download.', ['f' => strtoupper($this->format)]),
            route('files.show', ['path' => $path]),
            'bi-file-earmark-arrow-down', 'success',
        ));
    }

    protected function cell(mixed $value, ?string $type): mixed
    {
        if ($value === null) {
            return '';
        }

        if (in_array($type, ['date', 'datetime'], true) && is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $value;
        }

        return match ($type) {
            'money', 'number', 'percent' => is_numeric($value) ? (float) $value : $value,
            'integer' => (int) $value,
            'date' => format_date($value),
            'datetime' => format_date($value, true),
            default => is_string($value) ? trim($value) : $value,
        };
    }
}
