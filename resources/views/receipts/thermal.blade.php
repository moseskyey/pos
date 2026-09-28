<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->number }}</title>
    <style>@page { size: {{ $paper }} auto; margin: 0; }</style>
    @vite(['resources/scss/receipt.scss'])
</head>
<body class="receipt-body">
@unless ($embed)
    <div class="receipt-toolbar">
        <button onclick="window.print()">🖨 {{ __('Print') }}</button>
        <a class="secondary" href="{{ url()->previous() }}">{{ __('Back') }}</a>
    </div>
@endunless
<div class="receipt paper-{{ $paper }}">
    @if ($copy)<div class="watermark">{{ __('COPY') }}</div>@endif
    @if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif
    <div class="center bold big">{{ setting('business.name') }}</div>
    <div class="center">{{ $branch?->name }}{{ $branch?->address ? ', '.$branch->address : '' }}</div>
    @if ($branch?->phone || setting('business.phone'))<div class="center">{{ __('Tel') }}: {{ \App\Support\PhoneNumber::display($branch?->phone ?: setting('business.phone')) }}</div>@endif
    @if (setting('business.tin'))<div class="center">TIN: {{ setting('business.tin') }}@if (setting('business.vrn')) · VRN: {{ setting('business.vrn') }}@endif</div>@endif
    @if ($header)<div class="center" style="margin-top:3px">{{ $header }}</div>@endif
    <hr>
    <table>
        <tr><td>{{ $sale->status->value === 'quotation' ? __('Quotation') : __('Receipt') }}</td><td class="right bold">{{ $sale->number }}</td></tr>
        <tr><td>{{ __('Date') }}</td><td class="right">{{ $sale->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>{{ __('Cashier') }}</td><td class="right">{{ $sale->cashier?->name }}</td></tr>
        @if ($sale->register)<tr><td>{{ __('Till') }}</td><td class="right">{{ $sale->register->name }}</td></tr>@endif
        @if ($sale->customer)
            <tr><td>{{ __('Customer') }}</td><td class="right">{{ $sale->customer->name }}</td></tr>
            @if ($sale->customer->tin)<tr><td>{{ __('Customer TIN') }}</td><td class="right">{{ $sale->customer->tin }}</td></tr>@endif
        @endif
    </table>
    <hr>
    <table>
        @foreach ($sale->items as $item)
            <tr><td colspan="2" class="item-name">{{ $item->name }}</td></tr>
            <tr>
                <td>{{ qty($item->quantity) }} {{ $item->unit_name }} × {{ money($item->unit_price, false) }}</td>
                <td class="right">{{ money(\App\Support\Money::mul($item->quantity, $item->unit_price), false) }}</td>
            </tr>
            @if ($item->relationLoaded('serials') && $item->serials->isNotEmpty())
                <tr><td colspan="2">&nbsp;&nbsp;S/N: {{ $item->serials->pluck('serial')->join(', ') }}@if ($item->serials->first()->warranty_until)<br>&nbsp;&nbsp;{{ __('Warranty until :d', ['d' => format_date($item->serials->first()->warranty_until)]) }}@endif</td></tr>
            @endif
            @if ($item->promo_discount > 0)
                <tr><td>&nbsp;&nbsp;{{ $item->promotion_name ?: __('Promotion') }}</td><td class="right">-{{ money($item->promo_discount, false) }}</td></tr>
            @endif
            @if ($item->discount_amount > 0)
                <tr><td>&nbsp;&nbsp;{{ __('Discount') }}</td><td class="right">-{{ money($item->discount_amount, false) }}</td></tr>
            @endif
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ money($sale->subtotal, false) }}</td></tr>
        @if ($sale->discount_total > 0)<tr><td>{{ __('Discount') }}</td><td class="right">-{{ money($sale->discount_total, false) }}</td></tr>@endif
        @foreach ($taxBreakdown as $t)
            @if ((float) $t['rate'] > 0)
                <tr><td>{{ __('VAT :r%', ['r' => $t['rate']]) }} {{ setting('tax.prices_include_vat') ? '('.__('incl.').')' : '' }}</td><td class="right">{{ money($t['tax'], false) }}</td></tr>
            @endif
        @endforeach
        @if ($sale->rounding != 0)<tr><td>{{ __('Rounding') }}</td><td class="right">{{ money($sale->rounding, false) }}</td></tr>@endif
        <tr class="bold big"><td>{{ __('TOTAL') }}</td><td class="right">{{ money($sale->total) }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach ($sale->payments as $p)
            <tr><td>{{ $p->method->label() }}{{ $p->reference ? ' ('.$p->reference.')' : '' }}</td><td class="right">{{ money(data_get($p->meta, 'tendered', $p->amount), false) }}</td></tr>
            @if (data_get($p->meta, 'currency') === 'USD')
                <tr><td colspan="2" class="small">US$ {{ number_format((float) $p->meta['foreign_amount'], 2) }} @ {{ money($p->meta['rate'], false) }}</td></tr>
            @endif
        @endforeach
        @if ($sale->change_due > 0)<tr class="bold"><td>{{ __('Change') }}</td><td class="right">{{ money($sale->change_due, false) }}</td></tr>@endif
        @if ($sale->balance_due > 0)<tr class="bold"><td>{{ __('Balance due') }}</td><td class="right">{{ money($sale->balance_due, false) }}</td></tr>@endif
    </table>
    @if ($sale->customer && setting('loyalty.enabled') && ($sale->loyalty_earned || $sale->loyalty_redeemed))
        <hr><div class="center">{{ __('Points earned: :e · redeemed: :r · balance: :b', ['e' => $sale->loyalty_earned, 'r' => $sale->loyalty_redeemed, 'b' => $sale->customer->loyalty_points]) }}</div>
    @endif
    @if ($sale->prescription_ref)<hr><div>Rx: {{ $sale->prescription_ref }}@if ($sale->prescriber) · {{ $sale->prescriber }}@endif</div>@endif
    @if ($sale->note)<hr><div>{{ $sale->note }}</div>@endif
    @if ($sale->status->value === 'voided')<hr><div class="center bold big">*** {{ __('VOIDED') }} ***</div>@endif
    <hr>
    @if ($footer)<div class="center">{{ $footer }}</div>@endif
    @if ($sale->fiscal_code)<div class="center">{{ __('Verification code') }}: {{ $sale->fiscal_code }}</div>@endif
    @if ($qr)<img src="{{ $qr }}" class="qr" alt="QR">@endif
    <div class="center" style="font-size:.85em">{{ __('Powered by DukaPOS') }}</div>
</div>
</body>
</html>
