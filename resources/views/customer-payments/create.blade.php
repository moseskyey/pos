<x-layouts.app :title="__('Receive payment')" :breadcrumbs="[__('Payments') => route('customer-payments.index'), __('New')]">
    <x-page-header :title="__('Receive customer payment')" :subtitle="__('Payments are allocated to the oldest unpaid invoices (FIFO).')" />
    <form method="POST" action="{{ route('customer-payments.store') }}" x-data="{ customer: @js((string) old('customer_id', $selected)), balances: @js($customers->mapWithKeys(fn ($c) => [$c->id => (float) $c->balance])), method: @js(old('method', 'cash')) }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="row g-4">
            <div class="col-lg-7">
                <x-card :title="__('Payment')" icon="bi-cash">
                    <x-select name="customer_id" :label="__('Customer')" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' — '.money($c->balance)])" :value="$selected" :placeholder="__('Select a customer')" required x-model="customer" />
                    <div class="row">
                        <div class="col-md-6"><x-input name="amount" type="number" min="0" step="0.01" :label="__('Amount')" prefix="TSh" required x-bind:value="balances[customer] ?? ''" /></div>
                        <div class="col-md-6"><x-select name="method" :label="__('Method')" :options="$methods->mapWithKeys(fn ($m) => [$m->value => $m->label()])" required x-model="method" /></div>
                        <div class="col-md-6"><x-input name="reference" :label="__('Reference')" :placeholder="__('M-Pesa / bank reference / cheque no.')" /></div>
                        <div class="col-md-6" x-show="method === 'cheque'" x-cloak><x-input name="bank" :label="__('Bank')" x-bind:disabled="method !== 'cheque'" /></div>
                        <div class="col-md-6" x-show="method === 'cheque'" x-cloak><x-input name="cheque_date" type="date" :label="__('Cheque date')" :value="today()->toDateString()" :help="__('A later date makes it post-dated.')" x-bind:disabled="method !== 'cheque'" /></div>
                        <div class="col-md-6"><x-input name="note" :label="__('Note')" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-5">
                <div class="card bg-primary-soft border-0"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('Current balance') }}</div>
                    <div class="fs-2 fw-bold text-money" x-text="'TSh ' + Number(balances[customer] || 0).toLocaleString()"></div>
                    <p class="small text-body-secondary mb-0 mt-2">{{ __('Any amount above the balance becomes store credit. Cash payments are added to your open shift.') }}</p>
                </div></div>
            </div>
        </div>
        <x-form-actions :cancel="route('customer-payments.index')" :label="__('Record payment')" />
    </form>
</x-layouts.app>
