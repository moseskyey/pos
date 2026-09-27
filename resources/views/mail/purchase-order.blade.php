<x-mail::message>
# {{ __('Purchase order :n', ['n' => $order->number]) }}

{{ __('Dear :name,', ['name' => $order->supplier->contact_person ?: $order->supplier->name]) }}

{{ __('Please find our purchase order attached. Kindly confirm availability and the delivery date.') }}

@if ($note)
{{ $note }}
@endif

<x-mail::table>
| {{ __('Order total') }} | {{ __('Deliver to') }} | {{ __('Expected delivery') }} |
|:--|:--|:--|
| {{ money($order->total) }} | {{ $order->branch->name }} | {{ $order->expected_date ? format_date($order->expected_date) : '—' }} |
</x-mail::table>

{{ __('Asante,') }}<br>
{{ $business }}
</x-mail::message>
