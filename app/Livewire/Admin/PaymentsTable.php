<?php

namespace App\Livewire\Admin;

use App\Livewire\Tables\Column;
use App\Livewire\Tables\Filter;
use App\Models\Platform\SubscriptionPayment;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable extends AdminDataTable
{
    public ?int $tenantId = null;

    protected $listeners = ['payments-updated' => '$refresh'];

    protected function title(): string
    {
        return __('Subscription payments');
    }

    protected function query(): Builder
    {
        return SubscriptionPayment::query()->with(['tenant', 'plan', 'recorder'])
            ->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId));
    }

    protected function searchable(): array
    {
        return ['number', 'reference', 'provider_reference', 'phone', 'tenant.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('status', __('Status'), ['completed' => __('Completed'), 'processing' => __('Processing'), 'pending' => __('Pending'), 'failed' => __('Failed'), 'refunded' => __('Refunded')]),
            Filter::select('method', __('Method'), SubscriptionPayment::methodLabels()),
            Filter::dateRange('created_at', __('Date')),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Date'), 'created_at')->sortable()->date(true),
            Column::make(__('Business'), 'tenant.name')->visible(! $this->tenantId)
                ->html(fn ($p) => '<a href="'.route('admin.tenants.show', $p->tenant_id).'" class="fw-semibold text-reset">'.e($p->tenant?->name).'</a>')
                ->exportAs(fn ($p) => $p->tenant?->name),
            Column::make(__('Invoice'), 'number')->html(fn ($p) => '<span class="font-monospace small">'.e($p->number ?? '—').'</span><div class="small text-body-secondary">'.e($p->provider_reference).'</div>')
                ->exportAs(fn ($p) => $p->number),
            Column::make(__('Plan'), 'plan.name')->format(fn ($p) => ($p->plan?->name ?? '—').' · '.trans_choice(':count month|:count months', $p->months)),
            Column::make(__('Method'), 'method')->format(fn ($p) => SubscriptionPayment::methodLabels()[$p->method] ?? $p->method)
                ->html(fn ($p) => e(SubscriptionPayment::methodLabels()[$p->method] ?? $p->method).($p->recorder ? '<div class="small text-body-secondary">'.e(__('by :name', ['name' => $p->recorder->name])).'</div>' : '')),
            Column::make(__('Amount'), 'amount')->sortable()->money()->total(),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        $out = '';
        if ($row->method === 'fastlipa' && in_array($row->status, ['processing', 'failed', 'pending'], true)) {
            $out .= '<form method="POST" action="'.route('admin.payments.verify', $row).'" class="d-inline">'.csrf_field()
                .'<button class="btn btn-sm btn-light btn-icon" title="'.e(__('Check with FastLipa')).'" aria-label="'.e(__('Check with FastLipa')).'"><i class="bi bi-arrow-repeat"></i></button></form>';
        }
        if ($row->status === 'completed' && auth('admin')->user()?->is_super) {
            $out .= '<button type="button" class="btn btn-sm btn-light btn-icon text-danger" title="'.e(__('Refund')).'" aria-label="'.e(__('Refund')).'"'
                .' data-bs-toggle="modal" data-bs-target="#refundModal" data-action="'.route('admin.payments.refund', $row).'" data-label="'.e($row->number.' · '.money($row->amount)).'"><i class="bi bi-arrow-counterclockwise"></i></button>';
        }

        return $out ?: null;
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-cash-coin', 'title' => __('No payments found'), 'message' => __('Subscription payments appear here as businesses pay.')];
    }
}
