@props(['title', 'show' => false, 'size' => null, 'onClose' => 'closeForm', 'submit' => 'saveForm', 'saveNew' => false])
@if ($show)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="lwModalTitle" style="background: rgba(15,23,42,.5)"
         x-data x-init="$nextTick(() => $el.querySelector('input:not([type=hidden]),select,textarea')?.focus())" @keydown.escape.window="$el.querySelector('[data-close]')?.click()">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $size ? 'modal-'.$size : '' }}">
            <form class="modal-content" wire:submit="{{ $submit }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="lwModalTitle">{{ $title }}</h5>
                    <button type="button" class="btn-close" wire:click="{{ $onClose }}" data-close aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">{{ $slot }}</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" wire:click="{{ $onClose }}">{{ __('Cancel') }}</button>
                    @if ($saveNew)
                        <button type="button" class="btn btn-outline-primary" wire:click="{{ $submit }}(true)" wire:loading.attr="disabled">{{ __('Save & New') }}</button>
                    @endif
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading wire:target="{{ $submit }}" class="spinner-border"></span> {{ __('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
