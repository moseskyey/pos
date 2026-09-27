@extends('pdf.layout')

@section('header-right')
    <div><strong>{{ __('Ref') }}: {{ $sale->number }}</strong></div>
    <div class="muted">{{ __('Date') }}: {{ now()->format('d/m/Y') }}</div>
@endsection

@section('content')
    <div class="two-col">
        <div>
            <div class="muted" style="text-transform:uppercase;font-size:9px">{{ __('Deliver to') }}</div>
            <div class="bold" style="font-size:12px">{{ $sale->customer?->name ?? __('Walk-in customer') }}</div>
            <div>{{ $sale->customer?->address }}</div>
            <div>{{ $sale->customer?->phone ? \App\Support\PhoneNumber::display($sale->customer->phone) : '' }}</div>
        </div>
    </div>
    <table class="grid">
        <thead><tr><th>#</th><th>{{ __('Item') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Received') }}</th></tr></thead>
        <tbody>
        @foreach ($sale->items as $i => $item)
            <tr><td>{{ $i + 1 }}</td><td>{{ $item->name }}</td><td>{{ $item->sku }}</td><td class="text-end">{{ qty($item->quantity) }} {{ $item->unit_name }}</td><td class="text-end">______</td></tr>
        @endforeach
        </tbody>
    </table>
    <table style="width:100%;margin-top:50px"><tr>
        <td style="width:50%">{{ __('Delivered by') }}: ____________________<br><br>{{ __('Signature') }}: ____________________</td>
        <td style="width:50%">{{ __('Received by') }}: ____________________<br><br>{{ __('Signature & date') }}: ____________________</td>
    </tr></table>
@endsection
