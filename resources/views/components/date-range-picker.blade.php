@props(['from' => null, 'to' => null, 'preset' => null, 'live' => false, 'fromName' => 'from', 'toName' => 'to', 'presetName' => 'preset'])
@php
    $presets = ['today' => __('Today'), 'yesterday' => __('Yesterday'), 'week' => __('This Week'), 'month' => __('This Month'), 'last_month' => __('Last Month'), 'year' => __('This Year'), 'custom' => __('Custom')];
    $model = $live ? 'wire:model.live' : null;
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center" x-data="{ preset: @js($preset ?? 'custom') }">
    <select class="form-select form-select-sm w-auto" name="{{ $presetName }}" x-model="preset" aria-label="{{ __('Date range') }}"
            @if ($live) wire:model.live="{{ $presetName }}" @else onchange="this.form && this.value !== 'custom' && this.form.requestSubmit()" @endif>
        @foreach ($presets as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
        @endforeach
    </select>
    <div x-show="preset === 'custom'" x-cloak><div class="d-flex gap-1 align-items-center">
        <input type="date" class="form-control form-control-sm" name="{{ $fromName }}" value="{{ $from }}" aria-label="{{ __('From') }}" @if($live) wire:model.live="{{ $fromName }}" @endif>
        <span class="text-body-secondary small">–</span>
        <input type="date" class="form-control form-control-sm" name="{{ $toName }}" value="{{ $to }}" aria-label="{{ __('To') }}" @if($live) wire:model.live="{{ $toName }}" @endif>
        @unless ($live)<button class="btn btn-sm btn-primary" aria-label="{{ __('Apply') }}"><i class="bi bi-funnel"></i></button>@endunless
    </div></div>
</div>
