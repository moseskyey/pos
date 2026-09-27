@props(['label', 'value', 'icon' => 'bi-graph-up', 'color' => 'primary', 'trend' => null, 'hint' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card stat-card h-100 text-decoration-none text-reset'.($href ? ' hover-lift' : '')]) }}>
    <div class="card-body">
        <div class="stat-icon bg-{{ $color }}-soft text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
        <div class="min-w-0 flex-grow-1">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value text-money">{{ $value }}</div>
            @if ($trend !== null)
                @php $up = $trend >= 0; @endphp
                <div class="stat-trend">
                    <span class="badge rounded-pill text-bg-{{ $up ? 'success' : 'danger' }}-soft">
                        <i class="bi bi-arrow-{{ $up ? 'up' : 'down' }}-right"></i> {{ number_format(abs($trend), 1) }}%
                    </span>
                    <span class="text-body-secondary ms-1">{{ $hint ?? __('vs previous period') }}</span>
                </div>
            @elseif ($hint)
                <div class="stat-trend text-body-secondary">{{ $hint }}</div>
            @endif
        </div>
    </div>
</{{ $tag }}>
