<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ auth()->user()->theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="dp-user" content="{{ auth()->id() }}">
    <meta name="dp-sw" content="{{ asset('sw.js') }}">
    <meta name="dp-offline-ping" content="{{ route('pos.offline.ping') }}">
    <meta name="dp-offline-sync" content="{{ route('pos.offline.sync') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>{{ __('Offline till') }} · {{ setting('business.name') }}</title>
    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body class="pos-mode offline-till">
<div class="container-fluid py-3" x-data="offlineTill(@js([
        'userId' => auth()->id(),
        'catalogUrl' => route('pos.offline.catalog'),
        'pingUrl' => route('pos.offline.ping'),
        'syncUrl' => route('pos.offline.sync'),
        'posUrl' => route('pos'),
        'i18n' => [
            'noCatalog' => __('The product list has not been downloaded on this device yet. Open the offline till once while online.'),
            'saved' => __('Sale saved on this device. It will be sent when the connection is back.'),
            'synced' => __(':count offline sales synced.'),
            'short' => __('Payment is short.'),
            'reference' => __('Enter the transaction reference.'),
        ],
    ]))" x-cloak>
    <header class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 fw-bold mb-0 me-auto"><i class="bi bi-wifi-off text-warning"></i> {{ __('Offline till') }}
            <small class="text-body-secondary fw-normal fs-6" x-text="catalog ? catalog.branch.name + ' · ' + catalog.user.name : ''"></small></h1>
        <span class="badge rounded-pill" :class="online ? 'text-bg-success-soft' : 'text-bg-danger-soft'">
            <i class="bi" :class="online ? 'bi-wifi' : 'bi-wifi-off'"></i> <span x-text="online ? @js(__('Online')) : @js(__('Offline'))"></span>
        </span>
        <span class="badge rounded-pill text-bg-warning-soft" x-show="queue.length"><span x-text="queue.length"></span> {{ __('waiting to sync') }}</span>
        <button type="button" class="btn btn-sm btn-outline-primary" @click="sync()" :disabled="!online || syncing || !queue.length">
            <span x-show="syncing" class="spinner-border spinner-border-sm"></span> <i class="bi bi-cloud-upload"></i> {{ __('Sync now') }}
        </button>
        <a :href="posUrl" class="btn btn-sm btn-primary" x-show="online"><i class="bi bi-arrow-left"></i> {{ __('Back to online POS') }}</a>
    </header>

    <div class="alert alert-warning small" x-show="!catalog" x-text="i18n.noCatalog"></div>
    <div class="alert alert-info small py-2" x-show="catalog">
        <i class="bi bi-info-circle"></i> {{ __('Offline sales use the prices downloaded at') }} <strong x-text="catalog ? new Date(catalog.generated_at).toLocaleString() : ''"></strong>.
        {{ __('No discounts or credit sales offline. Receipt numbers are assigned when the sale syncs.') }}
    </div>
    <div class="alert alert-danger small" x-show="errors.length">
        <strong>{{ __('Some offline sales need attention:') }}</strong>
        <ul class="mb-0"><template x-for="e in errors" :key="e.client_id"><li><span class="font-monospace" x-text="e.ref"></span>: <span x-text="e.message"></span></li></template></ul>
    </div>

    <div class="row g-3" x-show="catalog && !done">
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <input type="search" x-ref="search" x-model="term" @keydown.enter.prevent="scan()" class="form-control form-control-lg mb-3"
                       placeholder="{{ __('Scan barcode or search name / SKU…') }}" aria-label="{{ __('Search products') }}" autofocus>
                <div class="list-group" style="max-height: 60vh; overflow:auto">
                    <template x-for="p in results" :key="p.id">
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" @click="add(p)">
                            <span><span class="fw-semibold" x-text="p.name"></span> <small class="text-body-secondary font-monospace" x-text="p.sku"></small></span>
                            <span class="text-nowrap"><span class="badge text-bg-light border me-2" x-show="p.track_stock" x-text="Number(p.stock).toLocaleString() + ' ' + (p.unit || '')"></span><strong x-text="fmt(p.price)"></strong></span>
                        </button>
                    </template>
                    <div class="text-center text-body-secondary py-4" x-show="term && !results.length">{{ __('No products found') }}</div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <h2 class="h6 fw-bold">{{ __('Cart') }}</h2>
                <div class="text-center text-body-secondary py-4" x-show="!cart.length">{{ __('Scan a barcode or tap a product to start.') }}</div>
                <template x-for="(l, i) in cart" :key="l.id">
                    <div class="d-flex align-items-center gap-2 border-bottom py-2">
                        <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate" x-text="l.name"></div><small class="text-body-secondary" x-text="fmt(unitPrice(l)) + ' × ' + l.qty"></small></div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light" @click="changeQty(i, -1)" aria-label="{{ __('Decrease') }}">−</button>
                            <input type="number" class="form-control form-control-sm text-center" style="width:70px" :step="l.decimal ? '0.001' : '1'" min="0" x-model.number="l.qty" aria-label="{{ __('Quantity') }}">
                            <button type="button" class="btn btn-light" @click="changeQty(i, 1)" aria-label="{{ __('Increase') }}">+</button>
                        </div>
                        <strong class="text-nowrap" style="min-width:90px;text-align:right" x-text="fmt(lineTotal(l) / 100, true)"></strong>
                        <button type="button" class="btn btn-sm btn-link text-danger" @click="cart.splice(i, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                </template>
                <div class="d-flex justify-content-between align-items-end mt-3">
                    <span class="text-body-secondary">{{ __('Total') }}</span>
                    <span class="display-6 fw-bold" x-text="fmt(total() / 100, true)"></span>
                </div>

                <div class="mt-3" x-show="cart.length">
                    <div class="row g-2">
                        <div class="col-5">
                            <select class="form-select" x-model="method" aria-label="{{ __('Payment method') }}">
                                <template x-for="m in catalog?.methods || []" :key="m.value"><option :value="m.value" x-text="m.label"></option></template>
                            </select>
                        </div>
                        <div class="col-7" x-show="!currentMethod()?.foreign">
                            <div class="input-group"><span class="input-group-text" x-text="catalog?.settings.symbol"></span>
                                <input type="number" min="0" step="0.01" class="form-control fw-bold" x-model.number="tendered" :placeholder="(total() / 100).toString()" aria-label="{{ __('Amount') }}"></div>
                        </div>
                        <div class="col-7" x-show="currentMethod()?.foreign">
                            <div class="input-group"><span class="input-group-text">US$</span>
                                <input type="number" min="0" step="0.01" class="form-control fw-bold" x-model.number="usd" aria-label="{{ __('Amount in US dollars') }}"></div>
                        </div>
                        <div class="col-12" x-show="currentMethod()?.reference">
                            <input type="text" class="form-control" x-model="reference" placeholder="{{ __('Transaction ID, e.g. SGH7K2L9QX') }}" aria-label="{{ __('Reference') }}">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 fs-5" x-show="change() > 0"><span>{{ __('Change') }}</span><strong class="text-success" x-text="fmt(change() / 100, true)"></strong></div>
                    <div class="text-danger small mt-2" x-text="error" x-show="error"></div>
                    <button type="button" class="btn btn-success btn-lg w-100 mt-3" @click="complete()"><i class="bi bi-check2-circle"></i> {{ __('Complete sale') }}</button>
                </div>
            </div></div>
        </div>
    </div>

    {{-- Receipt after an offline sale --}}
    <div class="row justify-content-center" x-show="done">
        <div class="col-md-6 col-lg-4">
            <div class="card text-center"><div class="card-body p-4">
                <div class="text-success display-4"><i class="bi bi-check-circle-fill"></i></div>
                <p class="mb-1" x-text="i18n.saved"></p>
                <div class="display-6 fw-bold my-3" x-show="done && done.change > 0">{{ __('Change') }} <span x-text="fmt((done?.change || 0) / 100, true)"></span></div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> {{ __('Print receipt') }}</button>
                    <button type="button" class="btn btn-success btn-lg" @click="newSale()">{{ __('New sale') }}</button>
                </div>
            </div></div>
            <div class="offline-receipt" x-show="done">
                <div class="center bold" x-text="catalog?.settings.business"></div>
                <div class="center" x-text="catalog?.branch.name"></div>
                <div class="center bold">{{ __('OFFLINE RECEIPT') }}</div>
                <div>{{ __('Ref') }}: <span x-text="done?.ref"></span></div>
                <div x-text="done ? new Date(done.sold_at).toLocaleString() : ''"></div>
                <hr>
                <template x-for="l in done?.lines || []" :key="l.product_id">
                    <div><div x-text="l.name"></div><div class="d-flex justify-content-between"><span x-text="l.qty + ' × ' + fmt(l.unit_price)"></span><span x-text="fmt(l.total / 100)"></span></div></div>
                </template>
                <hr>
                <div class="d-flex justify-content-between bold"><span>{{ __('TOTAL') }}</span><span x-text="fmt((done?.total || 0) / 100, true)"></span></div>
                <div class="d-flex justify-content-between" x-show="done?.change > 0"><span>{{ __('Change') }}</span><span x-text="fmt((done?.change || 0) / 100)"></span></div>
                <hr>
                <div class="center small">{{ __('The official receipt number is issued when the till reconnects.') }}</div>
                <div class="center small" x-text="catalog?.settings.footer"></div>
            </div>
        </div>
    </div>
</div>
@livewireScripts
</body>
</html>
