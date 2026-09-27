@extends('pdf.layout')

@section('header-right')
    <div class="muted">{{ __('Period') }}: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</div>
@endsection

@section('content')
    <div class="two-col">
        <div>
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Customer') }}</div>
            <div class="bold" style="font-size:12px">{{ $customer->name }}</div>
            <div>{{ $customer->address }}</div>
            <div>{{ $customer->phone ? \App\Support\PhoneNumber::display($customer->phone) : '' }}</div>
        </div>
        <div style="text-align:right">
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Balance due') }}</div>
            <div class="bold" style="font-size:18px">{{ money($closing) }}</div>
        </div>
    </div>
    <table class="grid">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
        <tbody>
            <tr><td>{{ $from->format('d/m/Y') }}</td><td class="bold">{{ __('Opening balance') }}</td><td></td><td></td><td class="text-end bold">{{ money($opening, false) }}</td></tr>
            @foreach ($entries as $e)
                <tr>
                    <td>{{ $e->created_at->format('d/m/Y') }}</td>
                    <td>{{ $e->note ?: \Illuminate\Support\Str::headline($e->type) }}</td>
                    <td class="text-end">{{ $e->debit > 0 ? money($e->debit, false) : '' }}</td>
                    <td class="text-end">{{ $e->credit > 0 ? money($e->credit, false) : '' }}</td>
                    <td class="text-end">{{ money($e->balance_after, false) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td></td><td>{{ __('Closing balance') }}</td><td class="text-end">{{ money($debits, false) }}</td><td class="text-end">{{ money($credits, false) }}</td><td class="text-end">{{ money($closing, false) }}</td></tr></tfoot>
    </table>
    <table class="grid" style="margin-top:16px">
        <thead><tr>@foreach (\App\Services\CustomerStatementService::agingBuckets() as $label)<th>{{ $label }}</th>@endforeach<th>{{ __('Total') }}</th></tr></thead>
        <tbody><tr>
            @foreach (array_keys(\App\Services\CustomerStatementService::agingBuckets()) as $k)<td>{{ money($aging[$k], false) }}</td>@endforeach<td class="bold">{{ money($aging['total'], false) }}</td>
        </tr></tbody>
    </table>
    <p class="muted" style="margin-top:18px">{{ __('Please pay via cash, M-Pesa or bank transfer. Asante kwa biashara!') }}</p>
@endsection
