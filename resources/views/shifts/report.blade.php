<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml"><title>{{ strtoupper($type) }} {{ $shift->number }}</title>
    <style>@page { size: {{ $paper }} auto; margin: 0; }</style>
    @vite(['resources/scss/receipt.scss'])
</head>
<body class="receipt-body">
<div class="receipt-toolbar"><button onclick="window.print()">🖨 {{ __('Print') }}</button></div>
<div class="receipt paper-{{ $paper }}">
    <div class="center bold big">{{ setting('business.name') }}</div>
    <div class="center">{{ $shift->branch?->name }}</div>
    <div class="center bold" style="margin-top:4px">{{ $type === 'x' ? __('X REPORT (mid-shift)') : __('Z REPORT (end of shift)') }}</div>
    <hr>
    <table>
        <tr><td>{{ __('Shift') }}</td><td class="right">{{ $shift->number }}</td></tr>
        <tr><td>{{ __('Cashier') }}</td><td class="right">{{ $shift->user->name }}</td></tr>
        <tr><td>{{ __('Till') }}</td><td class="right">{{ $shift->register->name }}</td></tr>
        <tr><td>{{ __('Opened') }}</td><td class="right">{{ $shift->opened_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>{{ $type === 'x' ? __('Printed') : __('Closed') }}</td><td class="right">{{ ($type === 'x' ? now() : $shift->closed_at)->format('d/m/Y H:i') }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>{{ __('Sales count') }}</td><td class="right">{{ $summary['sales_count'] }}</td></tr>
        <tr><td>{{ __('Gross sales') }}</td><td class="right">{{ money($summary['gross'], false) }}</td></tr>
        <tr><td>{{ __('Discounts') }}</td><td class="right">-{{ money($summary['discounts'], false) }}</td></tr>
        <tr class="bold"><td>{{ __('Net sales') }}</td><td class="right">{{ money($summary['net_sales'], false) }}</td></tr>
        <tr><td>{{ __('VAT') }}</td><td class="right">{{ money($summary['tax'], false) }}</td></tr>
        <tr><td>{{ __('Voids') }} ({{ $summary['voids']['count'] }})</td><td class="right">{{ money($summary['voids']['total'], false) }}</td></tr>
        <tr><td>{{ __('Returns') }} ({{ $summary['returns']['count'] }})</td><td class="right">{{ money($summary['returns']['total'], false) }}</td></tr>
    </table>
    <hr>
    <div class="bold">{{ __('Payments') }}</div>
    <table>
        @foreach ($summary['payments'] as $method => $p)
            <tr><td>{{ \App\Enums\PaymentMethod::from($method)->label() }} ({{ $p['count'] }})</td><td class="right">{{ money($p['total'], false) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <div class="bold">{{ __('Cash drawer') }}</div>
    <table>
        <tr><td>{{ __('Opening float') }}</td><td class="right">{{ money($summary['opening_float'], false) }}</td></tr>
        <tr><td>{{ __('Cash sales') }}</td><td class="right">{{ money($summary['cash_payments'], false) }}</td></tr>
        <tr><td>{{ __('Debt payments (cash)') }}</td><td class="right">{{ money($summary['customer_cash'] ?? 0, false) }}</td></tr>
        <tr><td>{{ __('Cash in') }}</td><td class="right">{{ money($summary['cash_in'], false) }}</td></tr>
        <tr><td>{{ __('Cash out') }}</td><td class="right">-{{ money($summary['cash_out'], false) }}</td></tr>
        <tr><td>{{ __('Cash refunds') }}</td><td class="right">-{{ money($summary['returns']['cash'], false) }}</td></tr>
        <tr><td>{{ __('Expenses from drawer') }}</td><td class="right">-{{ money($summary['expense_cash'] ?? 0, false) }}</td></tr>
        @if (\App\Support\Money::isPositive($summary['usd_change'] ?? 0))
            <tr><td>{{ __('Change given for USD') }}</td><td class="right">-{{ money($summary['usd_change'], false) }}</td></tr>
        @endif
        <tr class="bold"><td>{{ __('Expected cash') }}</td><td class="right">{{ money($summary['expected_cash'], false) }}</td></tr>
        @if (\App\Support\Money::isPositive($summary['expected_usd'] ?? 0))
            <tr class="bold"><td>{{ __('US dollars in drawer') }}</td><td class="right">US$ {{ number_format((float) $summary['expected_usd'], 2) }}</td></tr>
        @endif
        @if ($type === 'z')
            <tr class="bold"><td>{{ __('Counted cash') }}</td><td class="right">{{ money($shift->counted_cash, false) }}</td></tr>
            <tr class="bold big"><td>{{ __('Over / short') }}</td><td class="right">{{ money($shift->over_short, false) }}</td></tr>
        @endif
    </table>
    @if ($type === 'z' && $shift->denominations)
        <hr>
        <table>@foreach ($shift->denominations as $d => $c)<tr><td>{{ number_format($d) }} × {{ $c }}</td><td class="right">{{ number_format($d * $c) }}</td></tr>@endforeach</table>
    @endif
    <hr>
    <div class="center">{{ __('Cashier signature') }}: ______________</div>
    <div class="center" style="margin-top:6px">{{ __('Supervisor') }}: ______________</div>
</div>
</body>
</html>
