@props(['title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'page-header']) }}>
    <div class="min-w-0">
        <h2>{{ $title }}</h2>
        @if ($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endif
        {{ $meta ?? '' }}
    </div>
    @if (trim($slot) !== '')
        <div class="page-actions">{{ $slot }}</div>
    @endif
</div>
