@props(['status', 'label' => null])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $map = [
        'success' => ['completed', 'active', 'received', 'paid', 'approved', 'posted', 'closed', 'open', 'converted', 'sent', 'yes', 'in', 'confirmed'],
        'danger' => ['voided', 'cancelled', 'rejected', 'inactive', 'expired', 'overdue', 'failed', 'out', 'damaged', 'short'],
        'warning' => ['grace', 'held', 'pending', 'partial', 'partially_received', 'requested', 'counting', 'layaway', 'unpaid', 'low', 'dispatched', 'in_transit', 'over'],
        'info' => ['trial', 'quotation', 'draft_sent', 'processing', 'returned', 'partially_returned'],
        'primary' => ['new', 'ordered'],
    ];
    $color = 'secondary';
    foreach ($map as $c => $values) {
        if (in_array($value, $values, true)) { $color = $c; break; }
    }
    if ($value === 'open' && isset($attributes['shift'])) { $color = 'success'; }
    $text = $label ?? ($status instanceof \App\Enums\HasLabel ? $status->label() : __(\Illuminate\Support\Str::headline($value)));
@endphp
<span {{ $attributes->except('shift')->merge(['class' => "badge rounded-pill text-bg-{$color}-soft status-badge"]) }}>{{ $text }}</span>
