<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #111827; }
        .header { display: table; width: 100%; border-bottom: 3px solid #4F46E5; padding-bottom: 10px; margin-bottom: 14px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; }
        .brand { font-size: 18px; font-weight: bold; color: #4F46E5; }
        .muted { color: #6B7280; }
        .doc-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th { background: #EEF2FF; color: #3730A3; text-align: left; padding: 6px 7px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }
        table.grid td { padding: 6px 7px; border-bottom: 1px solid #E5E7EB; }
        table.grid tr:nth-child(even) td { background: #FAFAFB; }
        table.grid tfoot td { font-weight: bold; background: #F3F4F6; border-top: 2px solid #D1D5DB; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .box { border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px 10px; }
        .two-col { display: table; width: 100%; margin-bottom: 12px; }
        .two-col > div { display: table-cell; width: 50%; vertical-align: top; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #111827; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #9CA3AF; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #EEF2FF; color: #4338CA; font-size: 9px; font-weight: bold; }
        .watermark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 90px; color: rgba(220,38,38,.12); transform: rotate(-30deg); font-weight: bold; }
    </style>
</head>
<body>
@php $businessName = tenant() ? setting('business.name') : \App\Support\PlatformSettings::get('name', 'DukaPOS'); @endphp
@php $logo = tenant() && setting('business.logo') && \Illuminate\Support\Facades\Storage::disk('local')->exists(setting('business.logo')) ? 'data:image/png;base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get(setting('business.logo'))) : null; @endphp
<div class="header">
    <div class="left">
        @if ($logo)<img src="{{ $logo }}" style="max-height:48px;margin-bottom:4px"><br>@endif
        <div class="brand">{{ $businessName }}</div>
        <div class="muted">
            {{ ($branch ?? null)?->address ?? setting('business.address') }}<br>
            @if (setting('business.phone') || ($branch ?? null)?->phone){{ __('Tel') }}: {{ ($branch ?? null)?->phone ?? setting('business.phone') }}<br>@endif
            @if (setting('business.tin'))TIN: {{ setting('business.tin') }} @endif
            @if (setting('business.vrn')) · VRN: {{ setting('business.vrn') }}@endif
        </div>
    </div>
    <div class="right">
        <div class="doc-title">{{ $docTitle ?? $title }}</div>
        @yield('header-right')
    </div>
</div>
@yield('content')
<div class="footer">{{ $businessName }} · {{ __('Generated') }} {{ now()->format('d/m/Y H:i') }} · DukaPOS</div>
</body>
</html>
