<?php

namespace App\Livewire\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\ApprovalRequiredException;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresApproval;
use App\Models\Sale;
use App\Services\ReturnService;
use App\Support\Money;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReturnForm extends Component
{
    use RequiresApproval;

    public string $lookup = '';

    #[Locked]
    public ?int $saleId = null;

    public array $lines = [];

    public string $reason = '';

    public string $refundMethod = 'cash';

    public string $reference = '';

    public function mount(?int $sale = null): void
    {
        if ($sale) {
            $this->loadSale($sale);
        }
    }

    public function find(): void
    {
        $sale = Sale::query()->where('number', trim($this->lookup))->first();
        if (! $sale) {
            $this->addError('lookup', __('No sale found with that receipt number.'));

            return;
        }
        $this->loadSale($sale->id);
    }

    protected function loadSale(int $id): void
    {
        $sale = Sale::query()->with('items')->find($id);
        if (! $sale || $sale->status !== SaleStatus::Completed) {
            $this->addError('lookup', __('Only completed sales can be returned.'));

            return;
        }
        $this->saleId = $sale->id;
        $this->lookup = $sale->number;
        $this->lines = $sale->items->mapWithKeys(fn ($i) => [$i->id => [
            'name' => $i->name, 'sold' => (float) $i->quantity, 'returnable' => (float) $i->returnableQuantity(),
            'unit' => $i->unit_name, 'unit_refund' => (float) Money::div($i->netTotal(), $i->quantity), 'quantity' => 0, 'condition' => 'restock',
        ]])->all();
        $this->refundMethod = $sale->balance_due > 0 ? 'account' : 'cash';
    }

    public function returnAll(): void
    {
        foreach ($this->lines as $id => $line) {
            $this->lines[$id]['quantity'] = $line['returnable'];
        }
    }

    public function save(?string $approvalReason = null)
    {
        $this->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refundMethod' => ['required'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $return = app(ReturnService::class)->process(Sale::findOrFail($this->saleId), $this->lines, $this->reason, $this->refundMethod, auth()->user(), $this->reference ?: null, $this->approvals);
        } catch (ApprovalRequiredException $e) {
            $this->requestApproval($e->action, $e->permission, $e->getMessage(), 'save');

            return null;
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return null;
        }
        session()->flash('success', __('Return :n processed. Refund :a.', ['n' => $return->number, 'a' => money($return->refund_total)]));

        return $this->redirectRoute('returns.show', $return);
    }

    public function render()
    {
        $sale = $this->saleId ? Sale::with('customer')->find($this->saleId) : null;
        $total = collect($this->lines)->sum(fn ($l) => (float) ($l['quantity'] ?: 0) * $l['unit_refund']);
        $methods = ['cash' => __('Cash from drawer'), 'mpesa' => 'M-Pesa', 'tigopesa' => 'Mixx by Yas', 'airtel' => 'Airtel Money', 'halopesa' => 'HaloPesa', 'bank' => __('Bank transfer')];
        if ($sale?->customer_id) {
            $methods['store_credit'] = __('Store credit');
            $methods['account'] = __('Reduce customer debt');
        }

        return view('livewire.sales.return-form', compact('sale', 'total', 'methods'));
    }
}
