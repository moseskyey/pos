<x-mail::message>
# {{ $quotation ? __('Quotation :n', ['n' => $sale->number]) : __('Invoice :n', ['n' => $sale->number]) }}

{{ __('Dear :name,', ['name' => $sale->customer?->name ?? __('Customer')]) }}

{{ $quotation ? __('Thank you for your enquiry. Please find our quotation attached.') : __('Thank you for shopping with us. Your invoice is attached.') }}

@if ($note)
{{ $note }}
@endif

<x-mail::table>
@if ($quotation)
| {{ __('Total') }} | {{ __('Valid until') }} |
|:--|:--|
| {{ money($sale->total) }} | {{ $sale->valid_until ? format_date($sale->valid_until) : '—' }} |
@else
| {{ __('Total') }} | {{ __('Date') }} | {{ __('Balance due') }} |
|:--|:--|:--|
| {{ money($sale->total) }} | {{ format_date($sale->created_at, true) }} | {{ money($sale->balance_due) }} |
@endif
</x-mail::table>

{{ __('Asante,') }}<br>
{{ $business }}
</x-mail::message>
