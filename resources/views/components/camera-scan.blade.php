{{-- Camera scan button: fills the target input with the scanned code (Settings → Features → Camera scanning). --}}
@props(['target' => 'prev', 'enter' => true, 'submit' => false])
@if (feature('camera_scan'))
    <button type="button" {{ $attributes->merge(['class' => 'btn btn-outline-secondary']) }} data-camera-scan="{{ $target }}" @if ($enter) data-camera-enter @endif @if ($submit) data-camera-submit @endif
            aria-label="{{ __('Scan with camera') }}" title="{{ __('Scan with camera') }}">
        <i class="bi bi-camera" aria-hidden="true"></i>
    </button>
@endif
