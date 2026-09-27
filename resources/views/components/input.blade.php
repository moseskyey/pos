@props(['name' => null, 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'prefix' => null, 'suffix' => null, 'id' => null, 'size' => null, 'bag' => null])
@php if ($bag) { $errors = $errors->getBag($bag); } @endphp
@php
    $wire = $attributes->whereStartsWith('wire:model')->first();
    $key = $name ?? $wire;
    $errorKey = $key ? str_replace(['[', ']'], ['.', ''], $key) : null;
    $id = $id ?? ($key ? 'f_'.str_replace(['.', '[', ']'], '_', $key) : 'f_'.uniqid());
    $hasError = $errorKey && $errors->has($errorKey);
    $val = $wire || $type === 'password' || $type === 'file' ? null : old($errorKey ?? '', $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    @if ($prefix || $suffix)<div class="input-group {{ $hasError ? 'has-validation' : '' }} {{ $size ? 'input-group-'.$size : '' }}">@endif
        @if ($prefix)<span class="input-group-text">{!! $prefix !!}</span>@endif
        <input type="{{ $type }}" id="{{ $id }}" @if($name) name="{{ $name }}" @endif
               @if(! is_null($val) && $type !== 'file') value="{{ $val }}" @endif
               {{ $attributes->except('class')->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '').($size ? ' form-control-'.$size : '')]) }}
               @if($required) required @endif
               @if($help) aria-describedby="{{ $id }}_help" @endif>
        @if ($suffix)<span class="input-group-text">{!! $suffix !!}</span>@endif
        @if ($hasError)<div class="invalid-feedback">{{ $errors->first($errorKey) }}</div>@endif
    @if ($prefix || $suffix)</div>@endif
    @if ($help)<div id="{{ $id }}_help" class="form-text">{{ $help }}</div>@endif
</div>
