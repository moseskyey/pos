@props(['id', 'title' => null, 'size' => null, 'static' => false, 'centered' => true])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true"
     @if ($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif {{ $attributes->except('class') }}>
    <div class="modal-dialog {{ $centered ? 'modal-dialog-centered' : '' }} {{ $size ? 'modal-'.$size : '' }} modal-dialog-scrollable">
        <div class="modal-content {{ $attributes->get('class') }}">
            @if ($title)
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="{{ $id }}Label">{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
            @endif
            <div class="modal-body">{{ $slot }}</div>
            @isset($footer)<div class="modal-footer">{{ $footer }}</div>@endisset
        </div>
    </div>
</div>
