<x-layouts.app :title="__('Issue gift card')" :breadcrumbs="[__('Gift cards') => route('gift-cards.index'), __('New')]">
    <x-page-header :title="__('Issue gift card')" />
    <div x-data="{ kind: @js(old('kind', 'gift_card')) }">
    <form method="POST" action="{{ route('gift-cards.store') }}" x-data="dirtyForm">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Card')" icon="bi-gift">
                    <div class="row g-2 mb-3" role="radiogroup">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="kind" id="kind_gift" value="gift_card" x-model="kind">
                            <label class="btn btn-outline-primary w-100 py-3 text-start" for="kind_gift">
                                <span class="fw-semibold d-block"><i class="bi bi-cash-coin" aria-hidden="true"></i> {{ __('Gift card') }}</span>
                                <span class="small">{{ __('The customer pays for it now.') }}</span>
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="kind" id="kind_voucher" value="voucher" x-model="kind">
                            <label class="btn btn-outline-primary w-100 py-3 text-start" for="kind_voucher">
                                <span class="fw-semibold d-block"><i class="bi bi-ticket-perforated" aria-hidden="true"></i> {{ __('Voucher') }}</span>
                                <span class="small">{{ __('Complimentary, e.g. a prize or apology. No payment.') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-input name="value" type="number" min="1" step="1" :label="__('Value')" prefix="TSh" required /></div>
                        <div class="col-md-6"><x-input name="expires_on" type="date" :label="__('Expires on')" :min="now()->addDay()->toDateString()" :help="__('Optional. Leave empty for no expiry.')" /></div>
                        <div class="col-md-6"><x-select name="customer_id" :label="__('Customer')" :options="$customers" :placeholder="__('Not linked to a customer')" searchable /></div>
                        <div class="col-md-6"><x-input name="note" :label="__('Note')" :placeholder="__('e.g. Birthday gift from Mama Neema')" /></div>
                    </div>
                </x-card>
                <x-card :title="__('Payment')" icon="bi-wallet2" class="mt-4" x-show="kind === 'gift_card'">
                    <div class="row">
                        <div class="col-md-6"><x-select name="payment_method" :label="__('Paid by')" :options="$methods" value="cash" x-bind:disabled="kind !== 'gift_card'" /></div>
                        <div class="col-md-6"><x-input name="reference" :label="__('Reference')" :help="__('Needed for mobile money, card and bank.')" x-bind:disabled="kind !== 'gift_card'" /></div>
                    </div>
                    <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle"></i> {{ __('Cash goes into your open shift\'s drawer as a cash-in.') }}</p>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('How it works')" icon="bi-lightbulb">
                    <ul class="small text-body-secondary ps-3 mb-0">
                        <li>{{ __('A 12-character code is created. Print it or send it to the customer.') }}</li>
                        <li>{{ __('At the till choose "Gift card / voucher" and type or scan the code.') }}</li>
                        <li>{{ __('Customers can spend it in parts at any branch until the balance runs out.') }}</li>
                        <li>{{ __('Voided sales put the amount back on the card.') }}</li>
                    </ul>
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('gift-cards.index')" :label="__('Issue')" />
    </form>
    </div>
</x-layouts.app>
