@extends('pdf.layout')

@section('header-right')
    <div><strong>{{ $sale->number }}</strong></div>
    <div class="muted">{{ __('Date') }}: {{ $sale->created_at->format('d/m/Y') }}</div>
    @if ($sale->valid_until)<div class="muted">{{ __('Valid until') }}: {{ $sale->valid_until->format('d/m/Y') }}</div>@endif
    <div style="margin-top:4px"><span class="badge">{{ $sale->status->label() }}</span></div>
@endsection

@section('content')
    @if ($copy ?? false)<div class="watermark">{{ __('VOID') }}</div>@endif
    <div class="two-col">
        <div>
            <div class="muted" style="text-transform:uppercase;font-size:9px;letter-spacing:.5px">{{ __('Bill to') }}</div>
            @if ($sale->customer)
                <div class="bold" style="font-size:12px">{{ $sale->customer->name }}</div>
                @if ($sale->customer->address)<div>{{ $sale->customer->address }}</div>@endif
                @if ($sale->customer->phone)<div>{{ \App\Support\PhoneNumber::display($sale->customer->phone) }}</div>@endif
                @if ($sale->customer->tin)<div>TIN: {{ $sale->customer->tin }}</div>@endif
            @else
                <div class="bold">{{ __('Walk-in customer') }}</div>
            @endif
        </div>
        <div style="text-align:right">
            <div class="muted" style="text-transform:uppercase;font-size:9px;letter-spacing:.5px">{{ __('Served by') }}</div>
            <div>{{ $sale->cashier?->name }}</div>
            <div class="muted">{{ $branch?->name }}</div>
        </div>
    </div>

    <table class="grid">
        <thead><tr><th>#</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
        <tbody>
        @foreach ($sale->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->name }}<br><span class="muted">{{ $item->sku }}</span></td>
                <td class="text-end">{{ qty($item->quantity) }} {{ $item->unit_name }}</td>
                <td class="text-end">{{ money($item->unit_price, false) }}</td>
                <td class="text-end">{{ $item->discount_amount > 0 ? money($item->discount_amount, false) : '—' }}</td>
                <td class="text-end">{{ (float) $item->tax_rate }}%</td>
                <td class="text-end">{{ money($item->line_total, false) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="text-end">{{ money($sale->subtotal) }}</td></tr>
        @if ($sale->discount_total > 0)<tr><td>{{ __('Discount') }}</td><td class="text-end">-{{ money($sale->discount_total) }}</td></tr>@endif
        @foreach ($taxBreakdown as $t)
            <tr><td>{{ __('VAT :r%', ['r' => $t['rate']]) }} {{ __('on') }} {{ money($t['taxable'], false) }}</td><td class="text-end">{{ money($t['tax']) }}</td></tr>
        @endforeach
        @if ($sale->rounding != 0)<tr><td>{{ __('Rounding') }}</td><td class="text-end">{{ money($sale->rounding) }}</td></tr>@endif
        <tr class="grand"><td>{{ __('Total') }}</td><td class="text-end">{{ money($sale->total) }}</td></tr>
        @if ($sale->status->value !== 'quotation')
            <tr><td>{{ __('Paid') }}</td><td class="text-end">{{ money($sale->paid_total) }}</td></tr>
            @if ($sale->balance_due > 0)<tr><td class="bold">{{ __('Balance due') }}</td><td class="text-end bold">{{ money($sale->balance_due) }}</td></tr>@endif
        @endif
    </table>

    @if ($sale->payments->isNotEmpty())
        <div style="margin-top:14px" class="muted">{{ __('Payments') }}: @foreach ($sale->payments as $p){{ $p->method->label() }} {{ money($p->amount) }}{{ $p->reference ? ' ('.$p->reference.')' : '' }}@if (! $loop->last), @endif @endforeach</div>
    @endif
    @if ($sale->note)<div class="box" style="margin-top:12px">{{ $sale->note }}</div>@endif
    <table style="width:100%;margin-top:28px"><tr>
        <td style="width:70%" class="muted">{{ $footer }}</td>
        <td style="text-align:right">@if ($qr)<img src="{{ $qr }}" style="width:90px;height:90px">@endif</td>
    </tr></table>
@endsection
