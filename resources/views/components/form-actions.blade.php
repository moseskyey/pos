@props(['cancel' => url()->previous(), 'saveNew' => false, 'label' => null])
<div {{ $attributes->merge(['class' => 'sticky-actions']) }}>
    {{ $slot }}
    <a href="{{ $cancel }}" class="btn btn-light">{{ __('Cancel') }}</a>
    @if ($saveNew)
        <button type="submit" name="save_new" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> {{ __('Save & New') }}</button>
    @endif
    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2"></i> {{ $label ?? __('Save') }}</button>
</div>
