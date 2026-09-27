@props(['name' => null, 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'rows' => 3, 'id' => null])
@php
    $wire = $attributes->whereStartsWith('wire:model')->first();
    $key = $name ?? $wire;
    $errorKey = $key ? str_replace(['[', ']'], ['.', ''], $key) : null;
    $id = $id ?? ($key ? 'f_'.str_replace(['.', '[', ']'], '_', $key) : 'f_'.uniqid());
    $hasError = $errorKey && $errors->has($errorKey);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <textarea id="{{ $id }}" @if($name) name="{{ $name }}" @endif rows="{{ $rows }}"
              {{ $attributes->except('class')->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
              @if($required) required @endif>{{ $wire ? '' : old($errorKey ?? '', $value) }}</textarea>
    @if ($hasError)<div class="invalid-feedback">{{ $errors->first($errorKey) }}</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
