<div x-data="posTerminal({ userId: @js(device_key()) })" x-on:keydown.window="onKey($event)"
     x-on:scan-ok.window="dpBeep(true)" x-on:scan-fail.window="dpBeep(false)"
     x-on:focus-search.window="focusSearch()" x-on:sale-completed.window="afterSale($event.detail)">

    <div wire:ignore x-data="offlineStatus({ userId: @js(device_key()), offlineUrl: @js(route('pos.offline')) })" class="no-print">
        <div class="alert alert-danger d-flex align-items-center gap-2 m-2 mb-0 py-2" x-show="!online" x-cloak role="alert">
            <i class="bi bi-wifi-off fs-5"></i>
            <span class="me-auto">{{ __('Connection lost. Keep selling in the offline till; sales sync when the connection is back.') }}</span>
            <a :href="offlineUrl" class="btn btn-sm btn-light fw-semibold">{{ __('Open offline till') }}</a>
        </div>
        <div class="alert alert-warning d-flex align-items-center gap-2 m-2 mb-0 py-2" x-show="online && pending" x-cloak>
            <i class="bi bi-cloud-upload"></i><span class="me-auto"><span x-text="pending"></span> {{ __('offline sales waiting to sync') }}</span>
            <a :href="offlineUrl" class="btn btn-sm btn-light">{{ __('Review') }}</a>
        </div>
    </div>

    @if (! $branch)
        <div class="d-grid" style="min-height: calc(100vh - 64px); place-items: center">
            <x-empty-state icon="bi-shop" :title="__('Choose a branch')" :message="__('Select a single branch in the navbar to start selling.')" />
        </div>
    @elseif (! $this->shift)
        {{-- Open shift ------------------------------------------------------------}}
        <div class="d-grid p-3" style="min-height: calc(100vh - 64px); place-items: center">
            <div class="card shadow-lg" style="max-width: 440px; width: 100%">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-primary-soft text-primary d-grid mx-auto mb-3" style="width:72px;height:72px;place-items:center;font-size:2rem"><i class="bi bi-cash-coin"></i></div>
                        <h4 class="fw-bold mb-1">{{ __('Open your shift') }}</h4>
                        <p class="text-body-secondary mb-0">{{ $branch->name }} · {{ now()->format('d M Y, H:i') }}</p>
                    </div>
                    @can('shifts.open')
                        <form wire:submit="openShift">
                            <x-select wire:model="registerId" :label="__('Till / register')" :options="$registers" required />
                            <x-input wire:model="openingFloat" type="number" min="0" step="50" :label="__('Opening float (cash in drawer)')" prefix="TSh" required />
                            <button class="btn btn-primary btn-lg w-100" wire:loading.attr="disabled"><i class="bi bi-unlock"></i> {{ __('Open shift & start selling') }}</button>
                        </form>
                    @else
                        <div class="alert alert-warning mb-0">{{ __('You do not have permission to open a shift.') }}</div>
                    @endcan
                </div>
            </div>
        </div>
    @else
        <div class="pos">
            {{-- Catalogue ------------------------------------------------------------}}
            <section class="pos-catalog">
                <div class="d-flex gap-2 align-items-center">
                    <div class="pos-search flex-grow-1">
                        <i class="bi bi-upc-scan"></i>
                        <input type="search" x-ref="search" id="pos-search" class="form-control" autofocus autocomplete="off"
                               wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="scan"
                               placeholder="{{ __('Scan barcode or search name / SKU…') }}  (F2)" aria-label="{{ __('Search products') }}">
                        <span class="scan-indicator" wire:loading wire:target="scan,search"><span class="spinner-border spinner-border-sm text-primary"></span></span>
                    </div>
                    <x-camera-scan target="#pos-search" />
                    <div class="btn-group" role="group" aria-label="{{ __('View') }}">
                        <button type="button" class="btn btn-light {{ $view === 'grid' ? 'active' : '' }}" wire:click="$set('view', 'grid')" aria-label="{{ __('Grid view') }}"><i class="bi bi-grid-3x3-gap"></i></button>
                        <button type="button" class="btn btn-light {{ $view === 'list' ? 'active' : '' }}" wire:click="$set('view', 'list')" aria-label="{{ __('List view') }}"><i class="bi bi-list-ul"></i></button>
                    </div>
                </div>

                <div class="category-chips" role="tablist">
                    <button type="button" class="chip {{ ! $categoryId ? 'active' : '' }}" wire:click="$set('categoryId', null)">{{ __('All') }}</button>
                    @foreach ($this->categories as $cat)
                        <button type="button" class="chip {{ $categoryId === $cat->id ? 'active' : '' }}" wire:click="$set('categoryId', {{ $cat->id }})">{{ $cat->name }}</button>
                    @endforeach
                </div>

                <div class="product-grid {{ $view === 'list' ? 'list-view' : '' }}" wire:loading.class="opacity-75" wire:target="search,categoryId">
                    @forelse ($this->products as $p)
                        @php $out = $p->available !== null && $p->available <= 0; @endphp
                        <button type="button" class="product-card {{ $out ? 'out' : '' }}" wire:click="addProduct({{ $p->id }})" wire:key="p-{{ $p->id }}">
                            <div class="thumb">
                                @if ($p->image_path)<img src="{{ $p->imageUrl() }}" alt="" loading="lazy">@else<i class="bi bi-box-seam"></i>@endif
                            </div>
                            <div class="name">{{ $p->name }} @if ($p->requires_prescription && feature('pharmacy'))<span class="badge text-bg-danger-soft">Rx</span>@endif</div>
                            @if ($p->generic_name && feature('pharmacy'))<div class="small text-body-secondary text-truncate">{{ $p->generic_name }} {{ $p->strength }}</div>@endif
                            <div class="price">{{ money($p->display_price ?? $p->retail_price) }}</div>
                            @if ($p->available !== null)
                                <span class="stock badge rounded-pill {{ $out ? 'text-bg-danger' : ($p->available <= $p->reorder_level ? 'text-bg-warning' : 'text-bg-light border') }}">{{ qty($p->available) }}</span>
                            @endif
                        </button>
                    @empty
                        <div class="text-center text-body-secondary py-5" style="grid-column: 1 / -1">
                            <i class="bi bi-search fs-1 d-block mb-2"></i>{{ __('No products found') }}
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Cart ------------------------------------------------------------------}}
            <aside class="pos-cart" aria-label="{{ __('Cart') }}">
                @if ($quotationId)
                    <div class="alert alert-info rounded-0 mb-0 py-2 small border-0"><i class="bi bi-file-earmark-text"></i> {{ __('Converting quotation to a sale') }}</div>
                @endif
                @if ($completed)
                    <div class="pos-success flex-grow-1 d-flex flex-column justify-content-center">
                        <div class="check"><i class="bi bi-check-lg"></i></div>
                        <div class="text-body-secondary">{{ __('Sale :n completed', ['n' => $completed['number']]) }}</div>
                        <div class="text-body-secondary small mb-3">{{ __('Total') }} {{ money($completed['total']) }}{{ $completed['customer'] ? ' · '.$completed['customer'] : '' }}</div>
                        <div class="text-uppercase small fw-semibold text-body-secondary">{{ __('Change due') }}</div>
                        <div class="change-amount mb-2">{{ money($completed['change']) }}</div>
                        @if (! empty($completed['layaway']))
                            <div class="alert alert-warning mx-3 py-2 small">{{ __('Layaway saved. Balance due: :b', ['b' => money($completed['balance'])]) }}</div>
                        @endif
                        <div class="mb-3"></div>
                        <div class="d-grid gap-2 px-3">
                            <button type="button" class="btn btn-outline-primary btn-lg" @click="printReceipt(@js($completed['receipt']), @js($completed['escpos'] ?? null))"><i class="bi bi-printer"></i> {{ __('Print receipt') }}</button>
                            <div class="d-flex gap-2">
                                @if (! empty($completed['whatsapp']))<a href="{{ $completed['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-outline-success flex-fill"><i class="bi bi-whatsapp"></i> WhatsApp</a>@endif
                                @if ($completed['phone'])
                                    <button type="button" class="btn btn-outline-secondary flex-fill" wire:click="smsReceipt"><i class="bi bi-chat-dots"></i> {{ __('SMS receipt') }}</button>
                                @endif
                                @if (! empty($completed['email']) && feature('email_documents'))
                                    <button type="button" class="btn btn-outline-secondary flex-fill" wire:click="emailReceipt" wire:loading.attr="disabled"><i class="bi bi-envelope"></i> {{ __('Email') }}</button>
                                @endif
                            </div>
                            <button type="button" class="btn btn-success btn-lg" wire:click="newSale" x-ref="newSale"><i class="bi bi-plus-lg"></i> {{ __('New sale') }} <kbd class="ms-1">Enter</kbd></button>
                        </div>
                    </div>
                @else
                    <div class="cart-customer d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-light flex-grow-1 text-start d-flex align-items-center gap-2 min-w-0" wire:click="$set('modal', 'customer')">
                            <i class="bi bi-person-circle fs-5 text-primary"></i>
                            <span class="min-w-0">
                                <span class="d-block fw-semibold text-truncate">{{ $this->customer?->name ?? __('Walk-in customer') }}</span>
                                @if ($this->customer)
                                    <span class="d-block small text-body-secondary text-truncate">
                                        {{ $this->customer->isWholesale() ? __('Wholesale') : __('Retail') }}
                                        @if ($this->customer->balance > 0) · {{ __('Owes') }} {{ money($this->customer->balance) }}@endif
                                        @if ($loyaltyEnabled) · {{ $this->customer->loyalty_points }} {{ __('pts') }}@endif
                                    </span>
                                @endif
                            </span>
                            <kbd class="ms-auto">F4</kbd>
                        </button>
                        @if ($customerId)
                            <button type="button" class="btn btn-light btn-icon" wire:click="selectCustomer(null)" aria-label="{{ __('Remove customer') }}"><i class="bi bi-x-lg"></i></button>
                        @endif
                    </div>
                    @if ($this->salespeople->count() > 1)
                        <div class="px-3 pt-2 d-flex align-items-center gap-2">
                            <label for="pos-salesperson" class="small text-body-secondary text-nowrap mb-0"><i class="bi bi-person-badge"></i> {{ __('Sold by') }}</label>
                            <select id="pos-salesperson" class="form-select form-select-sm" wire:model.live="salespersonId">
                                <option value="">{{ auth()->user()->name }} ({{ __('me') }})</option>
                                @foreach ($this->salespeople as $person)
                                    @continue($person->id === auth()->id())
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="cart-lines">
                        @forelse ($cart as $key => $line)
                            @php $calc = $totals['lines'][$key] ?? null; @endphp
                            <div class="cart-line {{ $selectedLine === $key ? 'selected' : '' }}" wire:key="line-{{ $key }}" wire:click="selectLine('{{ $key }}')">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="min-w-0">
                                        <div class="line-name text-truncate">{{ $line['name'] }} @if (! empty($line['rx']))<span class="badge text-bg-danger-soft" title="{{ __('Prescription needed') }}">Rx</span>@endif</div>
                                        <div class="small text-body-secondary">
                                            {{ money($line['unit_price']) }} / {{ $line['unit'] }}
                                            @if ($line['tier'] === 'wholesale')<span class="badge text-bg-info-soft">{{ __('Wholesale') }}</span>@endif
                                            @if ($line['price_override'])<span class="badge text-bg-warning-soft">{{ __('Price changed') }}</span>@endif
                                            @if ($calc && $calc['discount_amount'] > 0)<span class="badge text-bg-success-soft">−{{ money($calc['discount_amount'], false) }}</span>@endif
                                        </div>
                                        @if ($calc && $calc['promo_discount'] > 0 && ! empty($line['promo_name']))
                                            <div class="small text-success text-truncate"><i class="bi bi-megaphone" aria-hidden="true"></i> {{ $line['promo_name'] }} <span class="text-money">−{{ money($calc['promo_discount'], false) }}</span></div>
                                        @endif
                                        @if (! empty($line['serialized']))
                                            <div class="mt-1" wire:click.stop>
                                                <div class="d-flex flex-wrap gap-1 mb-1">
                                                    @foreach ($line['serials'] as $si => $serial)
                                                        <span class="badge text-bg-secondary-soft font-monospace">{{ $serial }}
                                                            <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.5rem" wire:click="removeSerial('{{ $key }}', {{ $si }})" aria-label="{{ __('Remove :s', ['s' => $serial]) }}"></button></span>
                                                    @endforeach
                                                </div>
                                                <input type="text" class="form-control form-control-sm font-monospace {{ count($line['serials']) !== (int) $line['qty'] ? 'is-invalid' : 'is-valid' }}"
                                                       placeholder="{{ __('Scan serial / IMEI (:n of :q)', ['n' => count($line['serials']), 'q' => qty($line['qty'])]) }}" aria-label="{{ __('Serial / IMEI for :p', ['p' => $line['name']]) }}"
                                                       wire:keydown.enter.prevent="addSerial('{{ $key }}', $event.target.value)" x-on:keydown.enter="$nextTick(() => $el.value = '')" autocomplete="off">
                                            </div>
                                        @endif
                                    </div>
                                    <div class="fw-bold text-money text-end">{{ money($calc['line_total'] ?? 0) }}</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2" @click.stop>
                                    <div class="qty-control">
                                        <button type="button" wire:click="increment('{{ $key }}', -1)" aria-label="{{ __('Decrease') }}">−</button>
                                        <input type="number" step="{{ $line['decimal'] ? '0.001' : '1' }}" min="0" value="{{ $line['qty'] }}" wire:change="setQty('{{ $key }}', $event.target.value)" aria-label="{{ __('Quantity') }}" @keydown.enter="$event.target.blur()">
                                        <button type="button" wire:click="increment('{{ $key }}')" aria-label="{{ __('Increase') }}">+</button>
                                    </div>
                                    @if (count($line['units']) > 1)
                                        <select class="form-select form-select-sm w-auto" wire:change="changeUnit('{{ $key }}', $event.target.value)" aria-label="{{ __('Unit') }}">
                                            @foreach ($line['units'] as $u)
                                                <option value="{{ $u['id'] }}" @selected($u['id'] === $line['product_unit_id'])>{{ $u['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-light ms-auto" wire:click="openLine('{{ $key }}')" aria-label="{{ __('Edit line') }}"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeLine('{{ $key }}')" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-body-secondary py-5 px-3">
                                <i class="bi bi-cart3 d-block mb-2" style="font-size: 3rem; opacity: .35"></i>
                                <div class="fw-semibold">{{ __('Cart is empty') }}</div>
                                <div class="small">{{ __('Scan a barcode or tap a product to start.') }}</div>
                                <template x-if="restorable">
                                    <button type="button" class="btn btn-sm btn-soft-primary mt-3" @click="restore()"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore unsaved cart') }}</button>
                                </template>
                            </div>
                        @endforelse
                    </div>

                    <div class="cart-totals">
                        <div class="row-total"><span>{{ __('Subtotal') }} <span class="text-body-secondary">({{ qty($totals['items']) }} {{ __('items') }})</span></span><span class="text-money">{{ money($totals['subtotal']) }}</span></div>
                        @if ($totals['promo_discounts'] > 0)
                            <div class="row-total text-success"><span><i class="bi bi-megaphone" aria-hidden="true"></i> {{ __('Promotions') }}</span><span class="text-money">−{{ money($totals['promo_discounts']) }}</span></div>
                        @endif
                        @if ($totals['discount_total'] - $totals['promo_discounts'] > 0)
                            <div class="row-total text-success"><span>{{ __('Discount') }}</span><span class="text-money">−{{ money(\App\Support\Money::sub($totals['discount_total'], $totals['promo_discounts'])) }}</span></div>
                        @endif
                        <div class="row-total text-body-secondary"><span>{{ setting('tax.prices_include_vat') ? __('VAT (included)') : __('VAT') }}</span><span class="text-money">{{ money($totals['tax_total']) }}</span></div>
                        @if ($totals['rounding'] != 0)
                            <div class="row-total text-body-secondary"><span>{{ __('Rounding') }}</span><span class="text-money">{{ money($totals['rounding']) }}</span></div>
                        @endif
                        <div class="grand-total"><span class="label">{{ __('Total') }}</span><span class="amount text-money">{{ money($totals['total']) }}</span></div>
                    </div>

                    <div class="cart-actions">
                        <button type="button" class="btn btn-light" wire:click="openDiscount" @disabled(! $cart)><i class="bi bi-percent"></i>{{ __('Discount') }}<span class="shortcut">F6</span></button>
                        <button type="button" class="btn btn-light" wire:click="$set('modal', 'hold')" @disabled(! $cart)><i class="bi bi-pause-circle"></i>{{ __('Hold') }}<span class="shortcut">F8</span></button>
                        <button type="button" class="btn btn-light position-relative" wire:click="$set('modal', 'held')"><i class="bi bi-collection"></i>{{ __('Held') }}<span class="shortcut">F9</span>
                            @if ($this->heldSales->count())<span class="position-absolute top-0 end-0 badge rounded-pill bg-warning text-dark m-1">{{ $this->heldSales->count() }}</span>@endif
                        </button>
                        <button type="button" class="btn btn-light text-danger" wire:click="clearCart" wire:confirm="{{ __('Clear the cart?') }}" @disabled(! $cart)><i class="bi bi-x-circle"></i>{{ __('Clear') }}<span class="shortcut">&nbsp;</span></button>
                        <button type="button" class="btn btn-success btn-pay" wire:click="openPayment" @disabled(! $cart)>
                            <i class="bi bi-credit-card-2-front"></i> {{ __('Pay') }} {{ money($totals['total']) }} <kbd class="bg-transparent text-white border-white border-opacity-50">F10</kbd>
                        </button>
                    </div>
                @endif
            </aside>
        </div>

        {{-- Modals ------------------------------------------------------------------}}
        @if ($modal === 'customer')
            <x-livewire-modal :show="true" :title="__('Customer')" on-close="$set('modal', null)" submit="quickAddCustomer">
                <div class="position-relative mb-3">
                    <input type="search" class="form-control" wire:model.live.debounce.250ms="customerSearch" placeholder="{{ __('Search name or phone…') }}" autofocus>
                </div>
                <div class="list-group mb-3" style="max-height: 260px; overflow-y: auto">
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" wire:click="selectCustomer(null)">
                        <i class="bi bi-person"></i> {{ __('Walk-in customer') }}
                    </button>
                    @foreach ($this->customerResults as $c)
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $customerId === $c->id ? 'active' : '' }}" wire:click="selectCustomer({{ $c->id }})">
                            <span><span class="fw-semibold">{{ $c->name }}</span> <span class="small opacity-75">{{ $c->displayPhone() }}</span></span>
                            <span class="small">@if ($c->isWholesale())<span class="badge text-bg-info-soft">{{ __('Wholesale') }}</span>@endif @if ($c->balance > 0)<span class="badge text-bg-warning-soft">{{ money($c->balance) }}</span>@endif</span>
                        </button>
                    @endforeach
                </div>
                @can('customers.manage')
                    <div class="border-top pt-3">
                        <div class="fw-semibold small mb-2"><i class="bi bi-person-plus"></i> {{ __('Quick add') }}</div>
                        <div class="row g-2">
                            <div class="col-md-5"><x-input wire:model="newCustomer.name" :placeholder="__('Name')" class="mb-0" aria-label="{{ __('Name') }}" /></div>
                            <div class="col-md-4"><x-input wire:model="newCustomer.phone" placeholder="0712 345 678" class="mb-0" aria-label="{{ __('Phone') }}" /></div>
                            <div class="col-md-3"><x-select wire:model="newCustomer.type" :options="auth()->user()->can('customers.credit') ? ['retail' => __('Retail'), 'wholesale' => __('Wholesale')] : ['retail' => __('Retail')]" class="mb-0" aria-label="{{ __('Type') }}" /></div>
                        </div>
                    </div>
                @endcan
            </x-livewire-modal>
        @endif

        @if ($modal === 'discount')
            <x-livewire-modal :show="true" :title="__('Cart discount')" size="sm" on-close="$set('modal', null)" submit="applyCartDiscount">
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" id="dt-p" value="percent" wire:model.live="discountForm.type"><label class="btn btn-outline-primary" for="dt-p">%</label>
                    <input type="radio" class="btn-check" id="dt-f" value="fixed" wire:model.live="discountForm.type"><label class="btn btn-outline-primary" for="dt-f">TSh</label>
                </div>
                <x-input wire:model="discountForm.value" type="number" step="0.01" min="0" :label="__('Discount')" autofocus :help="__('Above :m% needs manager approval.', ['m' => setting('pos.max_discount_percent')])" />
                @if ($loyaltyEnabled && $this->customer && $this->customer->loyalty_points > 0)
                    <x-input wire:model.live="loyaltyPoints" type="number" min="0" :max="$this->customer->loyalty_points" :label="__('Redeem loyalty points (:p available)', ['p' => $this->customer->loyalty_points])" class="mb-0" />
                @endif
            </x-livewire-modal>
        @endif

        @if ($modal === 'line' && isset($cart[$lineForm['key']]))
            <x-livewire-modal :show="true" :title="$cart[$lineForm['key']]['name']" on-close="$set('modal', null)" submit="saveLine">
                <div class="row">
                    <div class="col-6"><x-input wire:model="lineForm.qty" type="number" step="0.001" min="0" :label="__('Quantity')" /></div>
                    <div class="col-6"><x-input wire:model="lineForm.unit_price" type="number" step="0.01" min="0" :label="__('Unit price')" prefix="TSh" :help="auth()->user()->can('sales.price_override') ? null : __('Changing the price needs approval.')" /></div>
                    <div class="col-5"><x-select wire:model="lineForm.discount_type" :label="__('Discount type')" :options="['percent' => '%', 'fixed' => 'TSh']" /></div>
                    <div class="col-7"><x-input wire:model="lineForm.discount_value" type="number" step="0.01" min="0" :label="__('Line discount')" /></div>
                </div>
                @if ($cart[$lineForm['key']]['price_override'])
                    <button type="button" class="btn btn-link btn-sm p-0" wire:click="resetLinePrice('{{ $lineForm['key'] }}')">{{ __('Reset to list price') }}</button>
                @endif
            </x-livewire-modal>
        @endif

        @if ($modal === 'hold')
            <x-livewire-modal :show="true" :title="__('Hold sale')" size="sm" on-close="$set('modal', null)" submit="hold">
                <x-input wire:model="holdNote" :label="__('Note (optional)')" :placeholder="__('e.g. Mama Neema – coming back')" autofocus class="mb-0" />
            </x-livewire-modal>
        @endif

        @if ($modal === 'held')
            <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.5)" @keydown.escape.window="$wire.set('modal', null)">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title fw-semibold">{{ __('Held sales') }}</h5><button type="button" class="btn-close" wire:click="$set('modal', null)"></button></div>
                        <div class="modal-body p-0">
                            @forelse ($this->heldSales as $held)
                                <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold text-truncate">{{ $held->hold_note }}</div>
                                        <div class="small text-body-secondary">{{ $held->customer?->name ?? __('Walk-in') }} · {{ trans_choice(':count item|:count items', $held->items_count) }} · {{ $held->created_at->diffForHumans() }}</div>
                                    </div>
                                    <div class="fw-bold text-money">{{ money($held->total) }}</div>
                                    <button class="btn btn-primary btn-sm" wire:click="resume({{ $held->id }})">{{ __('Resume') }}</button>
                                    <button class="btn btn-light btn-sm text-danger" wire:click="deleteHeld({{ $held->id }})" wire:confirm="{{ __('Delete this held sale?') }}"><i class="bi bi-trash"></i></button>
                                </div>
                            @empty
                                <x-empty-state icon="bi-collection" :title="__('No held sales')" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($modal === 'payment')
            <div class="modal fade show d-block pay-modal" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(15,23,42,.55)" @keydown.escape.window="$wire.set('modal', null)">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <form class="modal-content" wire:submit="checkout">
                        <div class="modal-header">
                            <div>
                                <div class="small text-body-secondary">{{ __('Amount due') }}</div>
                                <div class="pay-total text-money">{{ money($totals['total']) }}</div>
                            </div>
                            <button type="button" class="btn-close" wire:click="$set('modal', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                @foreach ($methods as $m)
                                    <div class="col-4 col-md-3">
                                        <button type="button" class="btn btn-outline-secondary w-100 method-btn {{ collect($payments)->contains('method', $m->value) ? 'active' : '' }}" wire:click="addPayment('{{ $m->value }}')">
                                            <i class="bi {{ $m->icon() }}"></i>{{ $m->label() }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            @foreach ($payments as $i => $p)
                                @php $method = \App\Enums\PaymentMethod::from($p['method']); @endphp
                                <div class="card mb-2 bg-surface" wire:key="pay-{{ $i }}-{{ $p['method'] }}">
                                    <div class="card-body py-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-3 fw-semibold"><i class="bi {{ $method->icon() }} text-primary"></i> {{ $method->label() }}</div>
                                            <div class="col-md-4">
                                                @if ($method->isForeign())
                                                    <div class="input-group"><span class="input-group-text">US$</span>
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-lg fw-bold" wire:model.live.debounce.300ms="payments.{{ $i }}.foreign_amount" aria-label="{{ __('Amount in US dollars') }}" @if ($loop->first) autofocus @endif>
                                                    </div>
                                                    <div class="small text-body-secondary mt-1">= {{ money($p['amount'] ?? 0) }} · {{ __('Rate :r', ['r' => money(setting('currency.usd_rate'))]) }}</div>
                                                @else
                                                    <div class="input-group"><span class="input-group-text">TSh</span>
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-lg fw-bold" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" aria-label="{{ __('Amount') }}" @if ($loop->first) autofocus @endif>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="col-md-4">
                                                @if ($method === \App\Enums\PaymentMethod::GiftCard)
                                                    <div class="input-group">
                                                        <input type="text" class="form-control font-monospace text-uppercase" wire:model="payments.{{ $i }}.reference" wire:keydown.enter.prevent="checkGiftCard({{ $i }})"
                                                               placeholder="XXXX-XXXX-XXXX" aria-label="{{ __('Gift card code') }}" autocomplete="off">
                                                        <button type="button" class="btn btn-outline-primary" wire:click="checkGiftCard({{ $i }})" wire:loading.attr="disabled">{{ __('Check') }}</button>
                                                    </div>
                                                    @if (isset($p['gift_balance']))
                                                        <div class="small text-success mt-1"><i class="bi bi-check-circle"></i> {{ __('Balance: :b', ['b' => money($p['gift_balance'])]) }}</div>
                                                    @endif
                                                @elseif ($method === \App\Enums\PaymentMethod::Cheque)
                                                    <input type="text" class="form-control" wire:model="payments.{{ $i }}.reference" placeholder="{{ __('Cheque number') }}" aria-label="{{ __('Cheque number') }}">
                                                    <div class="input-group input-group-sm mt-1">
                                                        <input type="text" class="form-control" wire:model="payments.{{ $i }}.bank" placeholder="{{ __('Bank') }}" aria-label="{{ __('Bank') }}">
                                                        <input type="date" class="form-control" wire:model="payments.{{ $i }}.cheque_date" aria-label="{{ __('Cheque date') }}" title="{{ __('Cheque date (post-dated if in the future)') }}">
                                                    </div>
                                                @elseif ($method->needsReference())
                                                    <input type="text" class="form-control" wire:model="payments.{{ $i }}.reference" placeholder="{{ $method->isMobileMoney() ? __('Transaction ID, e.g. SGH7K2L9QX') : __('Reference') }}" aria-label="{{ __('Reference') }}">
                                                    @if ($method->isMobileMoney() && $supportsPush)
                                                        @php $st = $p['intent_status'] ?? null; @endphp
                                                        <div class="input-group input-group-sm mt-1" @if (in_array($st, ['pending', 'processing']) || ! empty($p['intent_recheck'])) wire:poll.4s="pollStk" @endif>
                                                            <input type="text" class="form-control @error('payments.'.$i.'.phone') is-invalid @enderror" wire:model="payments.{{ $i }}.phone" placeholder="{{ $this->customer?->displayPhone() ?: '07XX XXX XXX' }}" aria-label="{{ __('Customer phone') }}" @disabled($st === 'completed')>
                                                            @if ($st === 'completed')
                                                                <span class="input-group-text text-success"><i class="bi bi-check-circle-fill"></i>&nbsp;{{ __('Confirmed') }}</span>
                                                            @elseif (in_array($st, ['pending', 'processing']))
                                                                <button type="button" class="btn btn-outline-secondary" wire:click="checkStkStatus({{ $i }})"><span class="spinner-border spinner-border-sm"></span> {{ __('Waiting…') }}</button>
                                                            @else
                                                                <button type="button" class="btn btn-outline-primary" wire:click="sendStkPush({{ $i }})"><i class="bi bi-phone-vibrate"></i> {{ $st === 'failed' ? __('Retry push') : __('Send push') }}</button>
                                                            @endif
                                                        </div>
                                                        @error('payments.'.$i.'.phone')<div class="text-danger small">{{ $message }}</div>@enderror
                                                        @if ($st === 'failed' && ! empty($p['intent_recheck']))
                                                            <div class="small text-warning-emphasis mt-1"><span class="spinner-grow spinner-grow-sm"></span> {{ __('Reported as failed, still checking. If the customer was charged it will confirm here: do not send another push.') }}</div>
                                                        @endif
                                                    @endif
                                                @elseif ($method === \App\Enums\PaymentMethod::Credit)
                                                    <span class="small {{ $this->customer ? 'text-body-secondary' : 'text-danger' }}">{{ $this->customer ? __('Limit :l · owes :b', ['l' => money($this->customer->credit_limit), 'b' => money($this->customer->balance)]) : __('Select a customer (F4)') }}</span>
                                                @elseif ($method === \App\Enums\PaymentMethod::StoreCredit)
                                                    <span class="small text-body-secondary">{{ __('Available: :a', ['a' => money($this->customer?->store_credit ?? 0)]) }}</span>
                                                @endif
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <button type="button" class="btn btn-light btn-sm text-danger" wire:click="removePayment({{ $i }})" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if (collect($payments)->contains('method', 'cash'))
                                <div class="quick-cash d-flex flex-wrap gap-2 my-3">
                                    <button type="button" class="btn btn-primary" wire:click="quickCash('exact')">{{ __('Exact') }}</button>
                                    @foreach (config('dukapos.quick_cash') as $note)
                                        <button type="button" class="btn btn-outline-primary" wire:click="quickCash({{ $note }})">+{{ number_format($note) }}</button>
                                    @endforeach
                                </div>
                            @endif

                            <div class="row g-3 mt-1">
                                <div class="col-4"><div class="small text-body-secondary">{{ __('Paid') }}</div><div class="fs-5 fw-bold text-money">{{ money($paid) }}</div></div>
                                <div class="col-4"><div class="small text-body-secondary">{{ __('Remaining') }}</div><div class="fs-5 fw-bold text-money {{ $remaining > 0 ? 'text-danger' : '' }}">{{ money($remaining) }}</div></div>
                                <div class="col-4 text-end"><div class="small text-body-secondary">{{ __('Change') }}</div><div class="change-due text-success text-money">{{ money($change) }}</div></div>
                            </div>
                            @if (collect($cart)->contains(fn ($l) => ! empty($l['rx'])))
                                <div class="alert alert-danger-subtle border-danger-subtle mt-3 mb-0 py-2">
                                    <div class="small fw-semibold mb-1"><i class="bi bi-capsule"></i> {{ __('Prescription needed for Rx items') }}</div>
                                    <div class="row g-2">
                                        <div class="col-md-6"><input type="text" class="form-control form-control-sm" wire:model="prescriptionRef" placeholder="{{ __('Prescription number') }}" aria-label="{{ __('Prescription number') }}"></div>
                                        <div class="col-md-6"><input type="text" class="form-control form-control-sm" wire:model="prescriber" placeholder="{{ __('Prescriber / hospital (optional)') }}" aria-label="{{ __('Prescriber') }}"></div>
                                    </div>
                                </div>
                            @endif
                            <input type="text" class="form-control form-control-sm mt-3" wire:model="note" placeholder="{{ __('Note on receipt (optional)') }}">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light me-auto" wire:click="$set('modal', null)">{{ __('Back') }} <kbd>Esc</kbd></button>
                            @if ($customerId && $remaining > 0 && $paid > 0 && feature('layaway') && auth()->user()->can('layaway.manage'))
                                <button type="button" class="btn btn-outline-warning" wire:click="checkoutLayaway" wire:loading.attr="disabled">
                                    <i class="bi bi-hourglass-split"></i> {{ __('Save as layaway') }}
                                </button>
                            @endif
                            <button type="submit" class="btn btn-success btn-lg px-5" wire:loading.attr="disabled" wire:target="checkout" @disabled($remaining > 0)>
                                <span wire:loading wire:target="checkout" class="spinner-border"></span>
                                <i class="bi bi-check2-circle" wire:loading.remove wire:target="checkout"></i> {{ __('Complete sale') }} <kbd class="bg-transparent text-white border-white border-opacity-50">Ctrl+Enter</kbd>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($modal === 'help')
            <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.5)" wire:click.self="$set('modal', null)">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title fw-semibold"><i class="bi bi-keyboard"></i> {{ __('Keyboard shortcuts') }}</h5><button type="button" class="btn-close" wire:click="$set('modal', null)"></button></div>
                        <div class="modal-body">
                            <table class="table table-sm mb-0">
                                @foreach ([['F2', __('Focus search / scanner')], ['F4', __('Choose customer')], ['F6', __('Cart discount')], ['F8', __('Hold sale')], ['F9', __('Held sales')], ['F10 / Ctrl+Enter', __('Pay')], ['Esc', __('Close window')], ['Del', __('Remove selected line')], ['+ / −', __('Change quantity of selected line')], ['Enter', __('New sale (after payment)')], ['?', __('This help')]] as [$k, $d])
                                    <tr><td style="width: 40%"><kbd>{{ $k }}</kbd></td><td>{{ $d }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <x-manager-pin-modal :approval="$approval" />

        <div class="position-fixed bottom-0 m-3 d-none d-lg-block no-print" style="z-index: 1030; left: 72px">
            <button type="button" class="btn btn-sm btn-light shadow-sm" wire:click="$set('modal', 'help')"><i class="bi bi-keyboard"></i> <kbd>?</kbd> {{ __('Shortcuts') }}</button>
            @if (setting('receipt.print_mode') === 'escpos')
                <span wire:ignore x-data="printerStatus({ drawerUrl: @js(route('pos.drawer')) })" class="d-inline-flex gap-1">
                    <template x-if="!supported"><span class="badge text-bg-warning-soft">{{ __('Direct printing needs Chrome or Edge') }}</span></template>
                    <template x-if="supported">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" :class="ready ? 'text-success' : 'text-danger'">
                                <i class="bi bi-printer"></i> <span x-text="ready ? @js(__('Printer ready')) : @js(__('Connect printer'))"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><button type="button" class="dropdown-item" @click="connect('usb')"><i class="bi bi-usb-symbol"></i> {{ __('USB printer') }}</button></li>
                                <li><button type="button" class="dropdown-item" @click="connect('serial')"><i class="bi bi-plug"></i> {{ __('Serial / COM / Bluetooth') }}</button></li>
                            </ul>
                            @can('cash.movements')
                                <button type="button" class="btn btn-light" @click="drawer()" title="{{ __('Open cash drawer (logged)') }}"><i class="bi bi-inbox"></i> {{ __('Drawer') }}</button>
                            @endcan
                        </div>
                    </template>
                </span>
            @endif
            <span class="badge text-bg-light border ms-1">{{ $this->shift->register->name }} · {{ $this->shift->number }}</span>
            <a href="{{ route('pos.offline') }}" class="btn btn-sm btn-light shadow-sm ms-1" title="{{ __('Keep selling without internet') }}"><i class="bi bi-wifi-off"></i> {{ __('Offline till') }}</a>
        </div>
        <iframe x-ref="printFrame" class="d-none" title="receipt"></iframe>

        {{-- Idle lock screen ----------------------------------------------------------}}
        <div wire:ignore x-data="idleLock({ minutes: {{ (int) setting('pos.lock_minutes', 0) }}, locked: @js((bool) session('pos_locked')), lockUrl: @js(route('lock')), verifyUrl: @js(route('lock.verify')) })">
            <template x-if="locked">
                <div class="pos-lock-screen d-grid" role="dialog" aria-modal="true" aria-label="{{ __('Terminal locked') }}">
                    <form class="card shadow-lg text-center" style="width: 340px" @submit.prevent="unlock()">
                        <div class="card-body p-4">
                            <x-avatar :user="auth()->user()" size="lg" class="mx-auto mb-3" />
                            <h5 class="fw-bold mb-1">{{ auth()->user()->name }}</h5>
                            <p class="text-body-secondary small mb-3"><i class="bi bi-lock-fill"></i> {{ __('Terminal locked. Enter your PIN to continue.') }}</p>
                            <input x-ref="pin" x-model="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off"
                                   class="form-control form-control-lg text-center fs-3 mb-2" style="letter-spacing: .5em" :class="error && 'is-invalid'"
                                   placeholder="••••" aria-label="{{ __('PIN') }}">
                            <div class="invalid-feedback d-block mb-2" x-text="error" x-show="error"></div>
                            <button class="btn btn-primary btn-lg w-100" :disabled="busy || pin.length < 4">
                                <span x-show="busy" class="spinner-border spinner-border-sm"></span> {{ __('Unlock') }}
                            </button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">@csrf
                        <button class="btn btn-link text-white text-decoration-none">{{ __('Switch user') }}</button>
                    </form>
                </div>
            </template>
        </div>
    @endif
</div>
