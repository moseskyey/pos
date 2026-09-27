@extends('pdf.layout')

@section('header-right')
    <div class="muted">{{ __('Period') }}: {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</div>
@endsection

@section('content')
    <div class="two-col">
        <div>
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Supplier') }}</div>
            <div class="bold" style="font-size:12px">{{ $supplier->name }}</div>
            @if ($supplier->contact_person)<div>{{ $supplier->contact_person }}</div>@endif
            <div>{{ $supplier->address }}</div>
            <div>{{ $supplier->phone ? \App\Support\PhoneNumber::display($supplier->phone) : '' }}</div>
            @if ($supplier->tin)<div>TIN {{ $supplier->tin }}</div>@endif
        </div>
        <div style="text-align:right">
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Balance owed') }}</div>
            <div class="bold" style="font-size:18px">{{ money($closing) }}</div>
        </div>
    </div>
    <table class="grid">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Billed') }}</th><th class="text-end">{{ __('Paid / returned') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
        <tbody>
            <tr><td>{{ $from->format('d/m/Y') }}</td><td class="bold">{{ __('Opening balance') }}</td><td></td><td></td><td class="text-end bold">{{ money($opening, false) }}</td></tr>
            @foreach ($entries as $e)
                <tr>
                    <td>{{ $e->created_at->format('d/m/Y') }}</td>
                    <td>{{ $e->note ?: \Illuminate\Support\Str::headline($e->type) }}</td>
                    <td class="text-end">{{ $e->credit > 0 ? money($e->credit, false) : '' }}</td>
                    <td class="text-end">{{ $e->debit > 0 ? money($e->debit, false) : '' }}</td>
                    <td class="text-end">{{ money($e->balance_after, false) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td></td><td>{{ __('Closing balance') }}</td><td class="text-end">{{ money($billed, false) }}</td><td class="text-end">{{ money($paid, false) }}</td><td class="text-end">{{ money($closing, false) }}</td></tr></tfoot>
    </table>
    <table class="grid" style="margin-top:16px">
        <thead><tr><th>{{ __('0–30 days') }}</th><th>{{ __('31–60 days') }}</th><th>{{ __('61–90 days') }}</th><th>{{ __('90+ days') }}</th><th>{{ __('Total') }}</th></tr></thead>
        <tbody><tr>
            <td>{{ money($aging['current'], false) }}</td><td>{{ money($aging['31_60'], false) }}</td><td>{{ money($aging['61_90'], false) }}</td><td>{{ money($aging['over_90'], false) }}</td><td class="bold">{{ money($aging['total'], false) }}</td>
        </tr></tbody>
    </table>
    <p class="muted" style="margin-top:18px">{{ __('Please confirm this statement against your records and report any differences.') }}</p>
@endsection
