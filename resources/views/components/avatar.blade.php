@props(['user' => null, 'name' => null, 'size' => ''])
@php
    $name = $name ?? $user?->name ?? '?';
    $url = $user?->avatarUrl();
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
@endphp
<span {{ $attributes->merge(['class' => 'avatar'.($size ? " avatar-$size" : '')]) }} aria-hidden="true">
    @if ($url)<img src="{{ $url }}" alt="">@else{{ $initials }}@endif
</span>
