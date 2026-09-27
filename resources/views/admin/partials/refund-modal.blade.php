@if (auth('admin')->user()->is_super)
    <x-modal id="refundModal" :title="__('Refund payment')"
             x-data x-on:show-bs-modal.dot="const b = $event.relatedTarget; $refs.form.action = b.dataset.action; $refs.label.textContent = b.dataset.label">
        <form method="POST" x-ref="form" id="refundForm">@csrf
            <p class="small">{{ __('Payment') }}: <strong x-ref="label"></strong></p>
            <p class="small text-body-secondary">{{ __('Marks the payment refunded. Send the money back through the original channel yourself.') }}</p>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="revoke" value="1" id="refundRevoke" checked>
                <label class="form-check-label" for="refundRevoke">{{ __('Also take back the months this payment added') }}</label>
            </div>
            <x-input name="note" :label="__('Reason')" required maxlength="500" class="mb-0" />
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" form="refundForm" class="btn btn-danger">{{ __('Refund') }}</button>
        </x-slot:footer>
    </x-modal>
@endif
