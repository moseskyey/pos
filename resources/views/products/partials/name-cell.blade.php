<div class="d-flex align-items-center gap-2">
    <span class="rounded-3 bg-surface d-grid flex-shrink-0 overflow-hidden border" style="width:40px;height:40px;place-items:center">
        @if ($row->image_path)
            <img src="{{ $row->imageUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover" loading="lazy">
        @else
            <i class="bi bi-box-seam text-body-secondary"></i>
        @endif
    </span>
    <div class="min-w-0">
        <div class="fw-semibold text-truncate" style="max-width: 260px">{{ $row->name }}</div>
        <div class="small text-body-secondary">
            {{ $row->unit?->short_name }}
            @if ($row->has_variants)<span class="badge text-bg-info-soft">{{ trans_choice(':count variant|:count variants', $row->variants_count) }}</span>@endif
            @if ($row->track_batches)<span class="badge text-bg-warning-soft">{{ __('Batches') }}</span>@endif
            @if (! $row->track_stock)<span class="badge text-bg-secondary-soft">{{ __('Service') }}</span>@endif
        </div>
    </div>
</div>
