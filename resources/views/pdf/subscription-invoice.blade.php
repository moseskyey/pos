@php $platform = \App\Support\PlatformSettings::get('name', 'DukaPOS'); @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->number }}</title>
    <style>
        @page { margin: 32px 36px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; }
        .header { display: table; width: 100%; border-bottom: 3px solid #4F46E5; padding-bottom: 12px; margin-bottom: 18px; }
        .header > div { display: table-cell; vertical-align: top; }
        .right { text-align: right; }
        .brand { font-size: 20px; font-weight: bold; color: #4F46E5; }
        .muted { color: #6B7280; }
        .title { font-size: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.grid th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 7px; font-size: 9.5px; text-transform: uppercase; }
        table.grid td { padding: 8px 7px; border-bottom: 1px solid #E5E7EB; }
        .total td { font-weight: bold; font-size: 13px; border-top: 2px solid #111827; }
        .paid { display: inline-block; padding: 3px 10px; border-radius: 10px; font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>
<div class="header">
    <div>
        <div class="brand">{{ $platform }}</div>
        <div class="muted">
            @if ($s = \App\Support\PlatformSettings::get('support_phone')){{ __('Tel') }}: {{ $s }}<br>@endif
            @if ($s = \App\Support\PlatformSettings::get('support_email')){{ $s }}@endif
        </div>
    </div>
    <div class="right">
        <div class="title">{{ __('Invoice') }}</div>
        <div>{{ $payment->number }}</div>
        <div class="muted">{{ $payment->paid_at?->format('d/m/Y') }}</div>
        <div style="margin-top:6px">
            @if ($payment->status === 'refunded')
                <span class="paid" style="background:#FEE2E2;color:#B91C1C">{{ __('REFUNDED') }}</span>
            @else
                <span class="paid" style="background:#DCFCE7;color:#15803D">{{ __('PAID') }}</span>
            @endif
        </div>
    </div>
</div>

<div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Billed to') }}</div>
<div style="font-size:13px;font-weight:bold">{{ $payment->tenant->name }}</div>
<div>{{ $payment->tenant->owner_name }}</div>
<div>{{ $payment->tenant->owner_email }}</div>
@if ($payment->tenant->owner_phone)<div>{{ \App\Support\PhoneNumber::display($payment->tenant->owner_phone) }}</div>@endif

<table class="grid">
    <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Period') }}</th><th style="text-align:right">{{ __('Amount') }}</th></tr></thead>
    <tbody>
    <tr>
        <td>{{ $platform }} — {{ $payment->plan?->name ?? __('Subscription') }} ({{ trans_choice(':count month|:count months', $payment->months) }})</td>
        <td>{{ $payment->period_start?->format('d/m/Y') }} – {{ $payment->period_end?->format('d/m/Y') }}</td>
        <td style="text-align:right">{{ money($payment->amount) }}</td>
    </tr>
    <tr class="total"><td colspan="2">{{ __('Total') }}</td><td style="text-align:right">{{ money($payment->amount) }}</td></tr>
    </tbody>
</table>

<p style="margin-top:18px">
    {{ __('Payment method') }}: {{ \App\Models\Platform\SubscriptionPayment::methodLabels()[$payment->method] ?? $payment->method }}
    @if ($payment->provider_reference) · {{ __('Reference') }}: {{ $payment->provider_reference }} @endif
</p>
<p class="muted" style="margin-top:30px">{{ __('Thank you for choosing :name.', ['name' => $platform]) }}</p>
</body>
</html>
