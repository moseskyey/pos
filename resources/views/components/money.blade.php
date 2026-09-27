@props(['amount', 'symbol' => true, 'colored' => false])
@php $neg = \App\Support\Money::isNegative($amount); @endphp
<span {{ $attributes->merge(['class' => 'text-money'.($colored ? ($neg ? ' text-danger' : ' text-success') : '')]) }}>{{ money($amount, $symbol) }}</span>
