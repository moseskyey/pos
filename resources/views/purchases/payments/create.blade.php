<x-layouts.app :title="__('Pay supplier')" :breadcrumbs="[__('Supplier bills') => route('supplier-bills.index'), __('Payment')]">
    <x-page-header :title="__('Pay supplier')" :subtitle="__('Leave allocations empty to pay the oldest bills first.')" />
    @if (! $selected)
        <div class="card" style="max-width: 560px"><div class="card-body">
            <form method="GET" action="{{ route('supplier-payments.create') }}">
                <x-select name="supplier" :label="__('Supplier')" :options="$suppliers->mapWithKeys(fn ($s) => [$s->id => $s->name.' — '.money($s->balance)])" :placeholder="__('Select a supplier')" required onchange="this.form.submit()" />
            </form>
            @if ($suppliers->isEmpty())<p class="text-body-secondary small mb-0">{{ __('You do not owe any supplier.') }}</p>@endif
        </div></div>
    @else
        @php $supplier = $suppliers->firstWhere('id', $selected) ?? \App\Models\Supplier::find($selected); @endphp
        <form method="POST" action="{{ route('supplier-payments.store') }}" x-data="dirtyForm">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $selected }}">
            <div class="row g-4">
                <div class="col-lg-7">
                    <x-card :title="__('Open bills')" :flush="true">
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>{{ __('Bill') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th><th style="width:150px">{{ __('Pay now') }}</th></tr></thead>
                            <tbody>
                            @forelse ($bills as $bill)
                                <tr>
                                    <td class="font-monospace small">{{ $bill->bill_no ?: '#'.$bill->id }}</td>
                                    <td class="small {{ $bill->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ format_date($bill->due_date) }}</td>
                                    <td class="text-end text-money">{{ money($bill->balance()) }}</td>
                                    <td><input type="number" step="0.01" min="0" max="{{ (float) $bill->balance() }}" name="allocations[{{ $bill->id }}]" class="form-control form-control-sm" placeholder="{{ __('auto') }}"></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-body-secondary small p-3">{{ __('No open bills. The payment reduces the account balance.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </x-card>
                </div>
                <div class="col-lg-5">
                    <x-card :title="$supplier->name" :subtitle="__('Balance :b', ['b' => money($supplier->balance)])">
                        <x-input name="amount" type="number" min="0" step="0.01" :label="__('Amount')" prefix="TSh" :value="(float) $supplier->balance" required />
                        <div x-data="{ method: @js(old('method', 'bank')) }">
                        <x-select name="method" :label="__('Method')" :options="$methods->mapWithKeys(fn ($m) => [$m->value => $m->label()])" value="bank" x-model="method" />
                        <x-input name="reference" :label="__('Reference')" x-bind:placeholder="method === 'cheque' ? @js(__('Cheque number')) : ''" />
                        <div x-show="method === 'cheque'" x-cloak><x-input name="bank" :label="__('Bank')" x-bind:disabled="method !== 'cheque'" /></div>
                        </div>
                        <x-input name="paid_at" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                        <x-toggle name="from_drawer" :label="__('Cash taken from my shift drawer')" />
                        <x-input name="note" :label="__('Note')" class="mb-0" />
                    </x-card>
                </div>
            </div>
            <x-form-actions :cancel="route('suppliers.show', $selected)" :label="__('Record payment')" />
        </form>
    @endif
</x-layouts.app>
