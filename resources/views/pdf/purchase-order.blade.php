@extends('pdf.layout')
@section('header-right')
    <div><strong>{{ $order->number }}</strong></div>
    <div class="muted">{{ __('Date') }}: {{ $order->order_date->format('d/m/Y') }}</div>
    @if ($order->expected_date)<div class="muted">{{ __('Deliver by') }}: {{ $order->expected_date->format('d/m/Y') }}</div>@endif
@endsection
@section('content')
    <div class="two-col">
        <div>
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Supplier') }}</div>
            <div class="bold" style="font-size:12px">{{ $order->supplier->name }}</div>
            <div>{{ $order->supplier->contact_person }}</div>
            <div>{{ $order->supplier->address }}</div>
            <div>{{ $order->supplier->displayPhone() }}</div>
            @if ($order->supplier->tin)<div>TIN: {{ $order->supplier->tin }}</div>@endif
        </div>
        <div style="text-align:right">
            <div class="muted" style="font-size:9px;text-transform:uppercase">{{ __('Deliver to') }}</div>
            <div class="bold">{{ $order->branch->name }}</div>
            <div>{{ $order->branch->address }}</div>
        </div>
    </div>
    <table class="grid">
        <thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end">{{ __('VAT') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
        <tbody>
        @foreach ($order->items as $i => $item)
            <tr><td>{{ $i + 1 }}</td><td>{{ $item->product->name }}<br><span class="muted">{{ $item->product->sku }}</span></td><td class="text-end">{{ qty($item->quantity) }} {{ $item->product->unit?->short_name }}</td>
                <td class="text-end">{{ money($item->unit_cost, false) }}</td><td class="text-end">{{ money($item->tax_amount, false) }}</td><td class="text-end">{{ money($item->line_total, false) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="text-end">{{ money($order->subtotal) }}</td></tr>
        <tr><td>{{ __('VAT') }}</td><td class="text-end">{{ money($order->tax_total) }}</td></tr>
        <tr class="grand"><td>{{ __('Total') }}</td><td class="text-end">{{ money($order->total) }}</td></tr>
    </table>
    @if ($order->note)<div class="box" style="margin-top:12px">{{ $order->note }}</div>@endif
    <p style="margin-top:40px">{{ __('Authorised by') }}: {{ $order->creator?->name }} ____________________</p>
@endsection
