<div class="toast-stack" aria-live="polite">
    @foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info', 'status' => 'success'] as $key => $type)
        @if (session($key))
            <span data-flash="{{ session($key) }}" data-type="{{ $type }}" hidden></span>
        @endif
    @endforeach
    @if ($errors->any() && ! session('error'))
        <span data-flash="{{ __('Please fix the highlighted errors.') }}" data-type="error" hidden></span>
    @endif
</div>
