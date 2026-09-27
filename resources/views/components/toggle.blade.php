@props(['name' => null, 'label' => null, 'checked' => false, 'help' => null, 'id' => null, 'value' => '1'])
@php
    $wire = $attributes->whereStartsWith('wire:model')->first();
    $key = $name ?? $wire;
    $errorKey = $key ? str_replace(['[', ']'], ['.', ''], $key) : null;
    $id = $id ?? ($key ? 'f_'.str_replace(['.', '[', ']'], '_', $key) : 'f_'.uniqid());
    $isChecked = $wire ? false : (bool) old($errorKey ?? '', $checked);
    if (! $wire && old('_token') !== null && $name) { $isChecked = (bool) old($errorKey); }
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    <div class="form-check form-switch">
        @if ($name && ! $wire)<input type="hidden" name="{{ $name }}" value="0">@endif
        <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" @if($name) name="{{ $name }}" @endif value="{{ $value }}"
               @checked($isChecked) {{ $attributes->except('class') }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    </div>
    @if ($help)<div class="form-text ms-5 ps-1">{{ $help }}</div>@endif
    @if ($errorKey && $errors->has($errorKey))<div class="invalid-feedback d-block">{{ $errors->first($errorKey) }}</div>@endif
</div>
