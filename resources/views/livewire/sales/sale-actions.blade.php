<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('receipts.reprint', $sale) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Reprint') }}</a>
    <div class="btn-group">
        <a href="{{ route('receipts.invoice', $sale) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> {{ __('Invoice') }}</a>
        <a href="{{ route('receipts.delivery-note', $sale) }}" target="_blank" class="btn btn-outline-secondary">{{ __('Delivery note') }}</a>
    </div>
    @if (feature('whatsapp') && in_array($sale->status->value, ['completed', 'layaway', 'converted']))
        <a href="{{ app(\App\Services\ShareService::class)->saleLink($sale) }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    @endif
    @if ($sale->customer?->phone)
        <button type="button" class="btn btn-outline-secondary" wire:click="sendSms"><i class="bi bi-chat-dots"></i> SMS</button>
    @endif
    @if ($sale->status->value === 'completed' && \Illuminate\Support\Facades\Route::has('returns.create') && $sale->items->contains(fn ($i) => $i->returnableQuantity() > 0))
        <a href="{{ route('returns.create', ['sale' => $sale->id]) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Return items') }}</a>
    @endif
    @if ($sale->status->value === 'layaway' && auth()->user()->can('layaway.manage'))
        <button type="button" class="btn btn-primary" wire:click="$set('showPayment', true)"><i class="bi bi-cash"></i> {{ __('Add payment') }}</button>
        <button type="button" class="btn btn-soft-danger" wire:click="$set('showVoid', true)">{{ __('Cancel layaway') }}</button>
    @endif
    @if ($sale->isVoidable())
        <button type="button" class="btn btn-soft-danger" wire:click="$set('showVoid', true)"><i class="bi bi-x-octagon"></i> {{ __('Void') }}</button>
    @endif

    <x-livewire-modal :show="$showVoid" :title="$sale->status->value === 'layaway' ? __('Cancel layaway') : __('Void sale :n', ['n' => $sale->number])" size="sm"
                      on-close="$set('showVoid', false)" :submit="$sale->status->value === 'layaway' ? 'cancelLayaway' : 'void'">
        <div class="alert alert-danger small py-2">
            {{ $sale->status->value === 'layaway' ? __('Reserved stock is returned and deposits move to store credit.') : __('Stock is restored and payments reversed. This cannot be undone.') }}
        </div>
        <x-textarea wire:model="voidReason" :label="__('Reason')" rows="2" required class="mb-0" />
    </x-livewire-modal>

    <x-livewire-modal :show="$showPayment" :title="__('Layaway payment')" size="sm" on-close="$set('showPayment', false)" submit="addLayawayPayment">
        <p class="small text-body-secondary">{{ __('Balance due') }}: <strong>{{ money($sale->balance_due) }}</strong></p>
        <x-select wire:model.live="payment.method" :label="__('Method')" :options="$methods->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" />
        <x-input wire:model="payment.amount" type="number" step="0.01" min="0" :label="__('Amount')" prefix="TSh" />
        @if (\App\Enums\PaymentMethod::from($payment['method'])->needsReference())
            <x-input wire:model="payment.reference" :label="__('Reference')" class="mb-0" />
        @endif
    </x-livewire-modal>

    <x-manager-pin-modal :approval="$approval" />
</div>
