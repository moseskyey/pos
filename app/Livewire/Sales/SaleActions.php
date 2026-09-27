<?php

namespace App\Livewire\Sales;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\RequiresApproval;
use App\Models\Sale;
use App\Models\User;
use App\Services\LayawayService;
use App\Services\ReceiptService;
use App\Services\SaleService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Void / layaway payment / SMS actions on the sale detail page.
 */
class SaleActions extends Component
{
    use RequiresApproval;

    #[Locked]
    public int $saleId;

    public string $voidReason = '';

    public bool $showVoid = false;

    public bool $showPayment = false;

    public array $payment = ['method' => 'cash', 'amount' => null, 'reference' => ''];

    public function mount(Sale $sale): void
    {
        $this->saleId = $sale->id;
        $this->payment['amount'] = (float) $sale->balance_due;
    }

    public function getSaleProperty(): Sale
    {
        return Sale::findOrFail($this->saleId);
    }

    public function void(?string $reason = null): void
    {
        $reason = $reason ?? $this->voidReason;
        $this->validate(['voidReason' => ['required', 'string', 'max:255']]);
        $user = auth()->user();
        $approver = $user->can('sales.void') ? $user : (isset($this->approvals['void']) ? User::find($this->approvals['void']) : null);
        if (! $approver) {
            $this->requestApproval('void', 'sales.void', __('Voiding a sale needs manager approval.'), 'void');

            return;
        }
        try {
            app(SaleService::class)->void($this->sale, $approver, $this->voidReason, $user);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }
        session()->flash('success', __('Sale voided and stock restored.'));
        $this->redirectRoute('sales.show', $this->saleId);
    }

    public function addLayawayPayment(LayawayService $layaways): void
    {
        abort_unless(auth()->user()->can('layaway.manage'), 403);
        $this->validate(['payment.method' => ['required'], 'payment.amount' => ['required', 'numeric', 'gt:0']]);
        try {
            $layaways->addPayment($this->sale, $this->payment['amount'], PaymentMethod::from($this->payment['method']), auth()->user(), $this->payment['reference'] ?: null);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }
        session()->flash('success', __('Payment recorded.'));
        $this->redirectRoute('sales.show', $this->saleId);
    }

    public function cancelLayaway(LayawayService $layaways): void
    {
        abort_unless(auth()->user()->can('layaway.manage'), 403);
        $this->validate(['voidReason' => ['required', 'string', 'max:255']]);
        $layaways->cancel($this->sale, auth()->user(), $this->voidReason);
        session()->flash('success', __('Layaway cancelled. Deposit moved to store credit.'));
        $this->redirectRoute('sales.show', $this->saleId);
    }

    public function sendSms(ReceiptService $receipts): void
    {
        $ok = $receipts->sendSms($this->sale);
        $this->dispatch('toast', message: $ok ? __('Receipt sent by SMS.') : __('Could not send SMS.'), type: $ok ? 'success' : 'error');
    }

    public function render()
    {
        return view('livewire.sales.sale-actions', [
            'sale' => $this->sale,
            'methods' => collect(PaymentMethod::enabled())->reject(fn ($m) => in_array($m, [PaymentMethod::Credit, PaymentMethod::StoreCredit], true)),
        ]);
    }
}
