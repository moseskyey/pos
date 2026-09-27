@php
    $changes = $row->attribute_changes ?? collect();
    $changes = $changes instanceof \Illuminate\Support\Collection ? $changes->toArray() : (array) $changes;
    $props = $row->properties instanceof \Illuminate\Support\Collection ? $row->properties->toArray() : (array) $row->properties;
    $new = $changes['attributes'] ?? [];
    $old = $changes['old'] ?? [];
@endphp
@if ($new)
    <div class="small">
        @foreach (array_slice($new, 0, 4, true) as $key => $value)
            <div class="text-truncate" style="max-width: 320px">
                <span class="text-body-secondary">{{ $key }}:</span>
                @if (array_key_exists($key, $old))<del class="text-danger">{{ is_scalar($old[$key]) ? \Illuminate\Support\Str::limit((string) $old[$key], 30) : json_encode($old[$key]) }}</del> →@endif
                <span class="text-success">{{ is_scalar($value) ? \Illuminate\Support\Str::limit((string) $value, 30) : json_encode($value) }}</span>
            </div>
        @endforeach
    </div>
@elseif ($props)
    <div class="small text-body-secondary text-truncate" style="max-width: 320px" title="{{ json_encode($props) }}">
        @foreach (array_slice($props, 0, 3, true) as $k => $v){{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last) · @endif @endforeach
    </div>
@else
    <span class="text-body-secondary">—</span>
@endif
