<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>{{ __('Barcode labels') }}</title>
    <style>
        @page { size: {{ $sizeKey === 'roll-50x25' ? '50mm 25mm' : 'A4' }}; margin: {{ $sizeKey === 'roll-50x25' ? '0' : '0' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #E5E7EB; }
        .toolbar { padding: 12px; text-align: center; font-family: system-ui, sans-serif; }
        .toolbar button { background: #4F46E5; color: #fff; border: 0; padding: 8px 18px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .sheet { background: #fff; margin: 0 auto 16px; display: grid; grid-template-columns: repeat({{ $size['cols'] }}, {{ $size['w'] }});
                 {{ $sizeKey === 'roll-50x25' ? 'width: 50mm;' : 'width: 210mm; min-height: 297mm; padding: 0; align-content: start; justify-content: center;' }} }
        .label { width: {{ $size['w'] }}; height: {{ $size['h'] }}; padding: 1.5mm 2mm; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; border: 1px dashed #eee; }
        .name { font-size: {{ in_array($sizeKey, ['a4-65']) ? '6.5pt' : '7.5pt' }}; font-weight: bold; line-height: 1.1; max-height: 2.2em; overflow: hidden; }
        .price { font-size: {{ in_array($sizeKey, ['a4-65']) ? '8pt' : '10pt' }}; font-weight: 800; margin-top: .5mm; }
        .barcode svg { width: 100%; height: {{ in_array($sizeKey, ['a4-65']) ? '7mm' : '9mm' }}; display: block; }
        .code { font-size: 6.5pt; letter-spacing: 1px; font-family: monospace; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; } .label { border: 0; } .sheet { page-break-after: always; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">🖨 {{ __('Print :n labels', ['n' => count($labels)]) }}</button></div>
@foreach (array_chunk($labels, $size['per_page']) as $page)
    <div class="sheet">
        @foreach ($page as $product)
            @php $code = $product->primaryBarcode() ?? $product->sku; @endphp
            <div class="label">
                @if ($showName)<div class="name">{{ \Illuminate\Support\Str::limit($product->name, 40) }}</div>@endif
                <div class="barcode">{!! \App\Support\BarcodeImage::svg($code, 180, 40) !!}</div>
                <div class="code">{{ $code }}</div>
                @if ($showPrice)<div class="price">{{ money($product->retail_price) }}</div>@endif
            </div>
        @endforeach
    </div>
@endforeach
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
