@props(['xlsx' => null, 'pdf' => null, 'print' => true])
<div class="btn-group">
    @if ($xlsx)<a href="{{ $xlsx }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> XLSX</a>@endif
    @if ($pdf)<a href="{{ $pdf }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>@endif
    @if ($print)<button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i></button>@endif
</div>
