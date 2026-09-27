<x-layouts.guest :title="__('Receipt verification')">
    <div class="text-center">
        <div class="rounded-circle bg-{{ $sale->status->value === 'voided' ? 'danger' : 'success' }}-soft text-{{ $sale->status->value === 'voided' ? 'danger' : 'success' }} d-grid mx-auto mb-3" style="width:72px;height:72px;place-items:center;font-size:2rem">
            <i class="bi {{ $sale->status->value === 'voided' ? 'bi-x-lg' : 'bi-patch-check-fill' }}"></i>
        </div>
        <h1 class="h4 fw-bold">{{ $sale->status->value === 'voided' ? __('This receipt was voided') : __('Genuine receipt') }}</h1>
        <p class="text-body-secondary">{{ setting('business.name') }} · {{ $sale->branch?->name }}</p>
    </div>
    <div class="card"><div class="card-body">
        <dl class="row mb-0 info-list">
            <dt class="col-5">{{ __('Receipt') }}</dt><dd class="col-7 font-monospace">{{ $sale->number }}</dd>
            <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $sale->created_at->format('d/m/Y H:i') }}</dd>
            <dt class="col-5">{{ __('Total') }}</dt><dd class="col-7 fw-bold">{{ money($sale->total) }}</dd>
            <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7 mb-0"><x-status-badge :status="$sale->status" /></dd>
        </dl>
    </div></div>
</x-layouts.guest>
