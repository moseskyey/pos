@props(['name' => null, 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false, 'searchable' => false, 'multiple' => false, 'id' => null, 'size' => null])
@php
    $wire = $attributes->whereStartsWith('wire:model')->first();
    $key = $name ?? $wire;
    $errorKey = $key ? str_replace(['[]', '[', ']'], ['', '.', ''], $key) : null;
    $id = $id ?? ($key ? 'f_'.str_replace(['.', '[', ']'], '_', $key) : 'f_'.uniqid());
    $hasError = $errorKey && ($errors->has($errorKey) || $errors->has($errorKey.'.*'));
    $selected = $wire ? null : old($errorKey ?? '', $value);
    $selected = collect(is_array($selected) || $selected instanceof \Illuminate\Support\Collection ? $selected : [$selected])->map(fn ($v) => (string) $v)->all();
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" @if($name) name="{{ $name }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '').($size ? ' form-select-'.$size : '')]) }}
            @if($searchable) data-tom-select @endif @if($multiple) multiple @endif @if($required) required @endif>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            @if (is_array($optLabel))
                <optgroup label="{{ $optValue }}">
                    @foreach ($optLabel as $v => $l)
                        <option value="{{ $v }}" @selected(in_array((string) $v, $selected, true))>{{ $l }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optValue }}" @selected(in_array((string) $optValue, $selected, true))>{{ $optLabel }}</option>
            @endif
        @endforeach
    </select>
    @if ($hasError)<div class="invalid-feedback d-block">{{ $errors->first($errorKey) ?: $errors->first($errorKey.'.*') }}</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
