@extends('pdf.layout')

@section('header-right')
    @foreach ($meta ?? [] as $label => $value)
        <div class="muted">{{ $label }}: {{ $value }}</div>
    @endforeach
@endsection

@section('content')
    <table class="grid">
        <thead>
        <tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td class="{{ is_numeric($cell) ? 'text-end' : '' }}">{{ is_float($cell) || (is_numeric($cell) && str_contains((string) $cell, '.')) ? number_format((float) $cell, 2) : (is_numeric($cell) && ! is_string($cell) ? number_format($cell) : $cell) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($headings) }}" class="text-center muted">{{ __('No records') }}</td></tr>
        @endforelse
        </tbody>
        @if ($totals)
            <tfoot>
            <tr>@foreach ($totals as $t)<td class="{{ is_numeric($t) ? 'text-end' : '' }}">{{ is_numeric($t) ? number_format((float) $t, 2) : $t }}</td>@endforeach</tr>
            </tfoot>
        @endif
    </table>
@endsection
