<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfExporter
{
    /**
     * Render a simple branded table PDF.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $totals
     */
    public static function table(string $title, array $headings, array $rows, ?array $totals = null, array $meta = [], string $orientation = 'portrait'): Response
    {
        $pdf = Pdf::loadView('pdf.table', compact('title', 'headings', 'rows', 'totals', 'meta'))
            ->setPaper('a4', count($headings) > 7 ? 'landscape' : $orientation);

        return $pdf->download(str($title)->slug().'-'.now()->format('Ymd-His').'.pdf');
    }
}
