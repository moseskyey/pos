@props(['name', 'label' => null, 'accept' => 'image/*', 'current' => null, 'help' => null])
@php $hasError = $errors->has($name); @endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }} x-data="fileUpload(@js($current))">
    @if ($label)<label class="form-label">{{ $label }}</label>@endif
    <div class="file-drop {{ $hasError ? 'border-danger' : '' }}" :class="{ dragover }" @click="pick" role="button" tabindex="0"
         @keydown.enter.prevent="pick" @dragover.prevent="dragover = true" @dragleave.prevent="dragover = false" @drop.prevent="drop($event)">
        <template x-if="preview"><img :src="preview" class="preview mb-2" alt=""></template>
        <div x-show="!preview">
            <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
        </div>
        <div class="small"><span class="fw-semibold text-primary">{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}</div>
        <div class="small text-body-secondary" x-text="fileName || @js($help ?? __('PNG, JPG or PDF up to 4MB'))"></div>
        <input type="file" name="{{ $name }}" accept="{{ $accept }}" class="d-none" x-ref="input" @change="change">
    </div>
    @if ($hasError)<div class="invalid-feedback d-block">{{ $errors->first($name) }}</div>@endif
</div>
