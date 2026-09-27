<?php

namespace App\Livewire\Tables;

use App\Models\Cheque;
use Illuminate\Database\Eloquent\Builder;

class ChequesTable extends DataTable
{
    protected string $defaultSort = 'cheque_date';

    protected string $defaultDirection = 'asc';

    protected function title(): string
    {
        return __('Cheques');
    }

    protected function query(): Builder
    {
        return Cheque::query()->with(['customer:id,name', 'supplier:id,name']);
    }

    protected function searchable(): array
    {
        return ['number', 'bank', 'customer.name', 'supplier.name'];
    }

    protected function filterDefinitions(): array
    {
        return [
            Filter::select('direction', __('Direction'), ['received' => __('Received from customers'), 'issued' => __('Issued to suppliers')]),
            Filter::select('status', __('Status'), ['pending' => __('Pending'), 'cleared' => __('Cleared'), 'bounced' => __('Bounced'), 'cancelled' => __('Cancelled')]),
            Filter::select('due', __('Due'), ['due' => __('Due now'), 'later' => __('Post-dated')])->query(fn ($q, $v) => $v === 'due'
                ? $q->where('status', 'pending')->whereDate('cheque_date', '<=', today())
                : $q->where('status', 'pending')->whereDate('cheque_date', '>', today())),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Cheque'), 'number')->sortable()->html(fn (Cheque $c) => '<span class="font-monospace fw-semibold">'.e($c->number).'</span><div class="small text-body-secondary">'.e($c->bank ?: '—').'</div>')
                ->exportAs(fn (Cheque $c) => $c->number),
            Column::make(__('Direction'), 'direction')->format(fn (Cheque $c) => $c->direction === 'received' ? __('Received') : __('Issued')),
            Column::make(__('From / to'))->format(fn (Cheque $c) => $c->customer?->name ?? $c->supplier?->name ?? __('Walk-in')),
            Column::make(__('Cheque date'), 'cheque_date')->sortable()->html(fn (Cheque $c) => e(format_date($c->cheque_date))
                .($c->status === 'pending' && $c->isPostDated() ? ' <span class="badge text-bg-info-soft">'.e(__('Post-dated')).'</span>' : '')
                .($c->status === 'pending' && ! $c->isPostDated() ? ' <span class="badge text-bg-warning-soft">'.e(__('Due')).'</span>' : ''))
                ->exportAs(fn (Cheque $c) => format_date($c->cheque_date)),
            Column::make(__('Amount'), 'amount')->sortable()->money()->total(),
            Column::make(__('Status'), 'status')->badge(),
        ];
    }

    protected function rowActions(mixed $row): ?string
    {
        if (! in_array($row->status, ['pending', 'cleared'], true) || ! auth()->user()->can('update', $row)) {
            return null;
        }
        $form = fn (string $status, string $label, string $class, ?string $confirm = null) => '<form method="POST" action="'.e(route('cheques.update', $row)).'" class="d-inline"'
            .($confirm ? ' data-confirm="'.e($confirm).'"' : '').'>'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'"><input type="hidden" name="_method" value="PUT">'
            .'<input type="hidden" name="status" value="'.$status.'"><button class="btn btn-sm '.$class.'">'.e($label).'</button></form>';

        $bounce = $form('bounced', __('Bounced'), 'btn-outline-danger', __('Mark cheque :n as bounced? The payment will be reversed.', ['n' => $row->number]));

        return $row->status === 'cleared' ? $bounce
            : '<div class="d-flex gap-1 justify-content-end">'.$form('cleared', __('Cleared'), 'btn-outline-success').$bounce
                .$form('cancelled', __('Cancel'), 'btn-light', __('Cancel cheque :n?', ['n' => $row->number])).'</div>';
    }

    protected function emptyState(): array
    {
        return ['icon' => 'bi-bank2', 'title' => __('No cheques yet'), 'message' => __('Cheques taken at the till or for payments appear here until they clear.')];
    }
}
