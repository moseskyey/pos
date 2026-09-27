@props(['icon' => 'bi-inbox', 'title' => null, 'message' => null, 'action' => null, 'actionLabel' => null, 'actionIcon' => 'bi-plus-lg'])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h5>{{ $title ?? __('Nothing here yet') }}</h5>
    @if ($message)<p>{{ $message }}</p>@endif
    {{ $slot }}
    @if ($action)
        <a href="{{ $action }}" class="btn btn-primary"><i class="bi {{ $actionIcon }}"></i> {{ $actionLabel }}</a>
    @endif
</div>
