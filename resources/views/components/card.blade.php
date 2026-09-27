@props(['title' => null, 'subtitle' => null, 'icon' => null, 'bodyClass' => '', 'flush' => false])
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-header">
            <div class="d-flex align-items-center gap-2 min-w-0">
                @if ($icon)<i class="bi {{ $icon }} text-primary"></i>@endif
                <div class="min-w-0">
                    <h3 class="card-title text-truncate">{{ $title }}</h3>
                    @if ($subtitle)<div class="small text-body-secondary fw-normal">{{ $subtitle }}</div>@endif
                </div>
            </div>
            @isset($actions)<div class="d-flex gap-2 flex-shrink-0">{{ $actions }}</div>@endisset
        </div>
    @endif
    @if ($flush)
        {{ $slot }}
    @else
        <div class="card-body {{ $bodyClass }}">{{ $slot }}</div>
    @endif
    @isset($footer)<div class="card-footer">{{ $footer }}</div>@endisset
</div>
