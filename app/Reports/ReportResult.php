<?php

namespace App\Reports;

class ReportResult
{
    /**
     * @param  array<int, array{label:string, value:string, icon?:string, color?:string, hint?:string}>  $kpis
     * @param  array<string, array{label:string, type?:string}>  $columns  key => definition (type: text|money|number|percent|date)
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public array $totals = [],
        public array $kpis = [],
        public ?array $chart = null,
        public ?string $note = null,
    ) {}
}
