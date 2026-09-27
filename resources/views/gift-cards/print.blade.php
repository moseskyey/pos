<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card->displayCode() }}</title>
    <style>
        @page { size: 86mm 54mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #f5f7fb; }
        .card { width: 86mm; height: 54mm; margin: 12px auto; padding: 5mm 6mm; border-radius: 3mm; color: #fff;
                background: linear-gradient(135deg, #4F46E5, #7C3AED); display: flex; flex-direction: column; justify-content: space-between; }
        .biz { font-weight: 700; font-size: 13pt; }
        .kind { font-size: 8pt; text-transform: uppercase; letter-spacing: .12em; opacity: .85; }
        .value { font-size: 20pt; font-weight: 800; }
        .code { font-family: ui-monospace, monospace; font-size: 12pt; letter-spacing: .08em; }
        .barcode { background: #fff; border-radius: 1.5mm; padding: 1mm 2mm; display: inline-block; }
        .meta { font-size: 7pt; opacity: .85; }
        .row { display: flex; justify-content: space-between; align-items: flex-end; }
        @media print { body { background: #fff; } .card { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="row">
            <div><div class="biz">{{ setting('business.name') }}</div><div class="kind">{{ $card->kind === 'voucher' ? __('Voucher') : __('Gift card') }}</div></div>
            <div class="value">{{ money($card->initial_value) }}</div>
        </div>
        <div class="row">
            <div>
                <div class="code">{{ $card->displayCode() }}</div>
                <div class="meta">{{ $card->expires_on ? __('Valid until :d', ['d' => format_date($card->expires_on)]) : __('No expiry') }} · {{ setting('business.phone') }}</div>
            </div>
            <div class="barcode"><img src="{{ \App\Support\BarcodeImage::dataUri($card->code, 110, 26) }}" alt="{{ $card->code }}"></div>
        </div>
    </div>
    <p class="no-print" style="text-align:center"><button onclick="window.print()">{{ __('Print') }}</button></p>
</body>
</html>
