<x-layouts.app :title="__('Serial numbers')" :breadcrumbs="[__('Inventory'), __('Serial numbers')]">
    <x-page-header :title="__('Serial / IMEI numbers')" :subtitle="__('Look up a phone or appliance by its serial to see who bought it and whether it is under warranty.')" />

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <x-card :title="__('Warranty look-up')" icon="bi-shield-check">
                <form method="GET" action="{{ route('serials.index') }}" class="d-flex gap-2 mb-3" role="search">
                    <input type="search" name="q" id="serial-lookup" value="{{ $query }}" class="form-control font-monospace" placeholder="{{ __('Scan or type serial / IMEI') }}" aria-label="{{ __('Serial / IMEI') }}" autofocus>
                    <x-camera-scan target="#serial-lookup" :enter="false" submit />
                    <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('Look up') }}</button>
                </form>
                @if ($query !== '')
                    @if ($found)
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="font-monospace fw-bold">{{ $found->serial }}</span>
                            <x-status-badge :status="$found->status" />
                            @if ($found->warranty_until)
                                <span class="badge {{ $found->underWarranty() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    <i class="bi bi-shield{{ $found->underWarranty() ? '-check' : '-x' }}"></i>
                                    {{ $found->underWarranty() ? __('Under warranty until :d', ['d' => format_date($found->warranty_until)]) : __('Warranty ended :d', ['d' => format_date($found->warranty_until)]) }}
                                </span>
                            @endif
                        </div>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4">{{ __('Product') }}</dt><dd class="col-sm-8"><a href="{{ route('products.show', $found->product_id) }}">{{ $found->product?->name }}</a></dd>
                            @if ($found->sale)
                                <dt class="col-sm-4">{{ __('Sold') }}</dt><dd class="col-sm-8">{{ format_date($found->sold_at, true) }} · <a href="{{ route('sales.show', $found->sale) }}" class="font-monospace">{{ $found->sale->number }}</a></dd>
                            @endif
                            @if ($found->customer)
                                <dt class="col-sm-4">{{ __('Customer') }}</dt><dd class="col-sm-8"><a href="{{ route('customers.show', $found->customer) }}">{{ $found->customer->name }}</a> {{ $found->customer->displayPhone() }}</dd>
                            @endif
                            @if ($found->note)<dt class="col-sm-4">{{ __('Note') }}</dt><dd class="col-sm-8">{{ $found->note }}</dd>@endif
                        </dl>
                    @else
                        <x-empty-state icon="bi-question-circle" :title="__('No unit with serial “:s”', ['s' => $query])" :message="__('Check the number, or record it below.')" />
                    @endif
                @endif
            </x-card>
        </div>
        @if ($products->isNotEmpty())
            <div class="col-lg-5">
                <x-card :title="__('Record serial numbers')" icon="bi-plus-circle" :subtitle="__('For stock already on the shelf at this branch.')">
                    <form method="POST" action="{{ route('serials.store') }}">
                        @csrf
                        <x-select name="product_id" :label="__('Product')" :options="$products" :placeholder="__('Choose a product…')" searchable required />
                        <x-textarea name="serials" :label="__('Serials / IMEIs')" rows="4" class="font-monospace" :placeholder="__('One per line, or scan them one after another')" required />
                        <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Record') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>

    <livewire:tables.serials-table />
</x-layouts.app>
