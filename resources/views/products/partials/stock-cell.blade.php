@php $q = $row->stock_qty ?? 0; @endphp
@if (! $row->track_stock)
    <span class="text-body-secondary">—</span>
@elseif ($q <= 0)
    <span class="badge rounded-pill text-bg-danger-soft">{{ qty($q) }}</span>
@elseif ($q <= $row->reorder_level)
    <span class="badge rounded-pill text-bg-warning-soft">{{ qty($q) }}</span>
@else
    <span class="fw-medium">{{ qty($q) }}</span>
@endif
