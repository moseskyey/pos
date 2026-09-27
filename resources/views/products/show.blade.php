@php
    $canCost = auth()->user()->can('products.view_cost');
    $totalStock = $stocks->sum('quantity');
@endphp
<x-layouts.app :title="$product->name" :breadcrumbs="[__('Products') => route('products.index'), $product->name]">
    <div class="page-header">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <div class="rounded-4 bg-white border d-grid flex-shrink-0 overflow-hidden" style="width:72px;height:72px;place-items:center">
                @if ($product->image_path)
                    <img src="{{ $product->imageUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover">
                @else
                    <i class="bi bi-box-seam fs-2 text-body-secondary"></i>
                @endif
            </div>
            <div class="min-w-0">
                <h2 class="text-truncate">{{ $product->name }}</h2>
                <div class="d-flex flex-wrap gap-2 mt-1 align-items-center small">
                    <span class="badge text-bg-secondary-soft font-monospace">{{ $product->sku }}</span>
                    <x-status-badge :status="$product->is_active ? 'active' : 'inactive'" />
                    @if ($product->category)<span class="text-body-secondary"><i class="bi bi-tag"></i> {{ $product->category->fullName() }}</span>@endif
                    @if ($product->brand)<span class="text-body-secondary"><i class="bi bi-award"></i> {{ $product->brand->name }}</span>@endif
                    @if ($product->parent)<a href="{{ route('products.show', $product->parent) }}" class="text-decoration-none"><i class="bi bi-diagram-2"></i> {{ __('Variant of :name', ['name' => $product->parent->name]) }}</a>@endif
                </div>
            </div>
        </div>
        <div class="page-actions">
            @can('products.labels')
                <a href="{{ route('labels.index', ['products' => $product->has_variants ? $product->variants->pluck('id')->join(',') : $product->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-upc"></i> {{ __('Labels') }}</a>
            @endcan
            @can('products.create')
                <a href="{{ route('products.create', ['copy' => $product->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-copy"></i> {{ __('Duplicate') }}</a>
            @endcan
            @can('update', $product)
                <a href="{{ route('products.edit', $product->parent ?? $product) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
            @endcan
            @can('delete', $product)
                <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="{{ __('Archive this product? It will no longer be available for sale.') }}">@csrf @method('DELETE')
                    <button class="btn btn-soft-danger" title="{{ __('Archive') }}"><i class="bi bi-archive"></i></button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Retail price')" :value="$product->has_variants ? __('Varies') : money($product->retail_price)" icon="bi-tag" /></div>
        @if ($canCost)
            <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Cost price')" :value="money($product->cost_price)" icon="bi-cash" color="warning" :hint="$product->marginPercent() !== null ? __('Margin :m%', ['m' => $product->marginPercent()]) : null" /></div>
        @else
            <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Wholesale price')" :value="$product->wholesale_price ? money($product->wholesale_price) : '—'" icon="bi-boxes" color="warning" /></div>
        @endif
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Stock (all branches)')" :value="$product->track_stock ? qty($product->has_variants ? $product->variants->sum('stock_qty') : $totalStock).' '.$product->unit?->short_name : __('Not tracked')" icon="bi-stack" color="info" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Reorder level')" :value="qty($product->reorder_level).' '.$product->unit?->short_name" icon="bi-bell" color="danger" /></div>
        @if ($product->track_serials)
            <div class="col-12">
                <div class="alert alert-light border d-flex flex-wrap gap-3 align-items-center small mb-0">
                    <span><i class="bi bi-upc-scan"></i> {{ __('Serial numbers') }}:</span>
                    <span>{{ __('In stock') }} <strong>{{ number_format($serialCounts['in_stock'] ?? 0) }}</strong></span>
                    <span>{{ __('Sold') }} <strong>{{ number_format($serialCounts['sold'] ?? 0) }}</strong></span>
                    <span>{{ __('Defective') }} <strong>{{ number_format($serialCounts['defective'] ?? 0) }}</strong></span>
                    @if ($product->warranty_months)<span>{{ trans_choice(':count month warranty|:count months warranty', $product->warranty_months) }}</span>@endif
                    @if (feature('serials'))<a href="{{ route('serials.index') }}" class="ms-auto">{{ __('Manage serials') }} →</a>@endif
                </div>
            </div>
        @endif
    </div>

    <ul class="nav nav-tabs-modern mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">{{ __('Overview') }}</button></li>
        @if ($product->has_variants)<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-variants" type="button">{{ __('Variants') }} <span class="badge text-bg-secondary-soft">{{ $product->variants->count() }}</span></button></li>@endif
        @if ($product->track_stock && ! $product->has_variants)
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stock" type="button">{{ __('Stock') }}</button></li>
            @if (\Illuminate\Support\Facades\Route::has('stock.movements'))<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-movements" type="button">{{ __('Movements') }}</button></li>@endif
        @endif
        @if ($salesChart)<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sales" type="button">{{ __('Sales') }}</button></li>@endif
        @if ($product->track_batches)<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-batches" type="button">{{ __('Batches') }} <span class="badge text-bg-secondary-soft">{{ $batches->count() }}</span></button></li>@endif
        @if ($product->is_bundle)<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-bundle" type="button">{{ __('Bundle items') }} <span class="badge text-bg-secondary-soft">{{ $bundle->count() }}</span></button></li>@endif
        @if ($branchPrices->isNotEmpty())<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-branch-prices" type="button">{{ __('Branch prices') }}</button></li>@endif
        @if (! $product->is_bundle && auth()->user()->canAny(['suppliers.view', 'purchases.view']))<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-suppliers" type="button">{{ __('Suppliers') }} <span class="badge text-bg-secondary-soft">{{ $productSuppliers->count() }}</span></button></li>@endif
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-prices" type="button">{{ __('Price history') }}</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-overview">
            <div class="row g-4">
                <div class="col-lg-7">
                    <x-card :title="__('Details')">
                        <dl class="row info-list mb-0">
                            <div class="col-sm-6"><dt>{{ __('Base unit') }}</dt><dd>{{ $product->unit?->label() ?? '—' }}</dd></div>
                            <div class="col-sm-6"><dt>{{ __('VAT') }}</dt><dd>{{ $product->tax_type?->label() }}</dd></div>
                            <div class="col-sm-6"><dt>{{ __('Wholesale price') }}</dt><dd>{{ $product->wholesale_price ? money($product->wholesale_price) : '—' }} @if ($product->wholesale_min_qty)<span class="text-body-secondary small">({{ __('from :q', ['q' => qty($product->wholesale_min_qty)]) }})</span>@endif</dd></div>
                            <div class="col-sm-6"><dt>{{ __('Tracking') }}</dt><dd>
                                {{ $product->track_stock ? __('Stock tracked') : __('Service (no stock)') }}{{ $product->track_batches ? ' · '.__('Batches & expiry') : '' }}{{ $product->is_weighted ? ' · '.__('Weighed') : '' }}
                            </dd></div>
                            <div class="col-12"><dt>{{ __('Description') }}</dt><dd class="mb-0">{{ $product->description ?: '—' }}</dd></div>
                        </dl>
                    </x-card>
                </div>
                <div class="col-lg-5">
                    <x-card :title="__('Barcodes & units')" :flush="true">
                        <ul class="list-group list-group-flush">
                            @forelse ($product->barcodes as $barcode)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="font-monospace">{{ $barcode->barcode }}</span>
                                    <span class="badge text-bg-secondary-soft">{{ $barcode->productUnit?->unit?->name ?? $product->unit?->name }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-body-secondary small">{{ __('No barcodes. The SKU can be scanned instead.') }}</li>
                            @endforelse
                            @foreach ($product->units as $pu)
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-surface">
                                    <span>1 {{ $pu->unit->name }} = {{ qty($pu->factor) }} {{ $product->unit?->short_name }}</span>
                                    <span class="fw-semibold">{{ money($pu->retail_price) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                </div>
            </div>
        </div>

        @if ($product->has_variants)
            <div class="tab-pane fade" id="tab-variants">
                <div class="card"><div class="table-responsive">
                    <table class="table table-hover table-stack">
                        <thead><tr><th>{{ __('Variant') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Stock') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach ($product->variants as $v)
                            <tr>
                                <td data-label="{{ __('Variant') }}"><a href="{{ route('products.show', $v) }}" class="fw-semibold text-decoration-none">{{ $v->variantLabel() }}</a></td>
                                <td data-label="{{ __('SKU') }}" class="font-monospace small">{{ $v->sku }}</td>
                                <td data-label="{{ __('Price') }}" class="text-end text-money">{{ money($v->retail_price) }}</td>
                                <td data-label="{{ __('Stock') }}" class="text-end">{{ qty($v->stock_qty ?? 0) }}</td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$v->is_active ? 'active' : 'inactive'" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div></div>
            </div>
        @endif

        @if ($product->track_stock && ! $product->has_variants)
            <div class="tab-pane fade" id="tab-stock">
                <div class="card">
                    @if ($stocks->isEmpty())
                        <x-empty-state icon="bi-stack" :title="__('No stock recorded yet')" :message="__('Receive goods or post opening stock to start tracking.')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-stack">
                                <thead><tr><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Quantity') }}</th>@if ($canCost)<th class="text-end">{{ __('Value at cost') }}</th>@endif<th class="text-end">{{ __('Value at retail') }}</th><th>{{ __('Status') }}</th></tr></thead>
                                <tbody>
                                @foreach ($stocks as $s)
                                    <tr>
                                        <td data-label="{{ __('Branch') }}" class="fw-semibold">{{ $s->branch->name }}</td>
                                        <td data-label="{{ __('Quantity') }}" class="text-end">{{ qty($s->quantity) }} {{ $product->unit?->short_name }}</td>
                                        @if ($canCost)<td data-label="{{ __('Value at cost') }}" class="text-end text-money">{{ money(\App\Support\Money::mul($s->quantity, $product->cost_price)) }}</td>@endif
                                        <td data-label="{{ __('Value at retail') }}" class="text-end text-money">{{ money(\App\Support\Money::mul($s->quantity, $product->retail_price)) }}</td>
                                        <td data-label="{{ __('Status') }}">
                                            @if ($s->quantity <= 0)<x-status-badge status="out" :label="__('Out of stock')" />
                                            @elseif ($s->quantity <= $product->reorder_level)<x-status-badge status="low" :label="__('Low stock')" />
                                            @else<x-status-badge status="active" :label="__('In stock')" />@endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            @if (\Illuminate\Support\Facades\Route::has('stock.movements'))
                <div class="tab-pane fade" id="tab-movements">
                    <livewire:tables.stock-movements-table :product-id="$product->id" />
                </div>
            @endif
        @endif

        @if ($salesChart)
            <div class="tab-pane fade" id="tab-sales">
                <x-card :title="__('Units sold — last 30 days')">
                    <div class="chart-box"><canvas data-chart='@json($salesChart)'></canvas></div>
                </x-card>
            </div>
        @endif

        @if ($product->track_batches)
            <div class="tab-pane fade" id="tab-batches">
                <div class="card">
                    @if ($batches->isEmpty())
                        <x-empty-state icon="bi-calendar2-x" :title="__('No batches in stock')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-stack">
                                <thead><tr><th>{{ __('Batch') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Expiry') }}</th><th class="text-end">{{ __('Quantity') }}</th><th>{{ __('Status') }}</th></tr></thead>
                                <tbody>
                                @foreach ($batches as $b)
                                    <tr>
                                        <td data-label="{{ __('Batch') }}" class="font-monospace">{{ $b->batch_no }}</td>
                                        <td data-label="{{ __('Branch') }}">{{ $b->branch->name }}</td>
                                        <td data-label="{{ __('Expiry') }}">{{ format_date($b->expiry_date) }}</td>
                                        <td data-label="{{ __('Quantity') }}" class="text-end">{{ qty($b->quantity) }}</td>
                                        <td data-label="{{ __('Status') }}"><x-status-badge :status="$b->expiryStatus()" /></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="tab-pane fade" id="tab-prices">
            <div class="card">
                @if ($priceHistory->isEmpty())
                    <x-empty-state icon="bi-graph-up" :title="__('No price changes recorded')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-stack">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Field') }}</th><th class="text-end">{{ __('Old') }}</th><th class="text-end">{{ __('New') }}</th><th>{{ __('By') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                            <tbody>
                            @foreach ($priceHistory as $h)
                                @continue($h->field === 'cost_price' && ! $canCost)
                                <tr>
                                    <td data-label="{{ __('Date') }}">{{ format_date($h->created_at, true) }}</td>
                                    <td data-label="{{ __('Field') }}">{{ \Illuminate\Support\Str::headline($h->field) }}</td>
                                    <td data-label="{{ __('Old') }}" class="text-end text-money text-body-secondary">{{ $h->old_value !== null ? money($h->old_value) : '—' }}</td>
                                    <td data-label="{{ __('New') }}" class="text-end text-money fw-semibold">{{ $h->new_value !== null ? money($h->new_value) : '—' }}</td>
                                    <td data-label="{{ __('By') }}">{{ $h->user?->name ?? __('System') }}</td>
                                    <td data-label="{{ __('Reason') }}" class="small">{{ $h->reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($product->is_bundle)
            <div class="tab-pane fade" id="tab-bundle">
                <div class="card">
                    @if ($bundle->isEmpty())
                        <x-empty-state icon="bi-box2-heart" :title="__('No items in this bundle')" :message="__('Edit the product to add the items it contains.')" :action="route('products.edit', $product)" :action-label="__('Edit product')" action-icon="bi-pencil" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-stack mb-0">
                                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Per bundle') }}</th>@if ($canCost)<th class="text-end">{{ __('Cost') }}</th>@endif<th class="text-end">{{ __('In stock here') }}</th><th class="text-end">{{ __('Bundles possible') }}</th></tr></thead>
                                <tbody>
                                @foreach ($bundle as $row)
                                    @php $c = $row['item']->component; @endphp
                                    <tr>
                                        <td data-label="{{ __('Item') }}"><a href="{{ route('products.show', $c) }}">{{ $c->name }}</a></td>
                                        <td data-label="{{ __('Per bundle') }}" class="text-end">{{ qty($row['item']->quantity) }} {{ $c->unit?->short_name }}</td>
                                        @if ($canCost)<td data-label="{{ __('Cost') }}" class="text-end text-money">{{ money(\App\Support\Money::mul($row['item']->quantity, $c->cost_price)) }}</td>@endif
                                        <td data-label="{{ __('In stock here') }}" class="text-end">{{ $c->track_stock && $row['available'] !== null ? qty($row['available']) : '—' }}</td>
                                        <td data-label="{{ __('Bundles possible') }}" class="text-end fw-semibold">{{ $c->track_stock && $row['available'] !== null ? number_format(max(0, floor((float) $row['available'] / (float) $row['item']->quantity))) : '∞' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($branchPrices->isNotEmpty())
            <div class="tab-pane fade" id="tab-branch-prices">
                <form method="POST" action="{{ route('products.branch-prices', $product) }}" class="card" x-data="dirtyForm">
                    @csrf @method('PUT')
                    <div class="card-body">
                        <p class="small text-body-secondary">{{ __('Leave a branch empty to use the normal price (:r retail).', ['r' => money($product->retail_price)]) }}</p>
                        <div class="table-responsive">
                            <table class="table table-stack align-middle mb-3">
                                <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Retail price') }}</th><th>{{ __('Wholesale price') }}</th></tr></thead>
                                <tbody>
                                @foreach ($branchPrices as $row)
                                    @php $id = $row['branch']->id; @endphp
                                    <tr>
                                        <td data-label="{{ __('Branch') }}" class="fw-semibold">{{ $row['branch']->name }}</td>
                                        <td data-label="{{ __('Retail price') }}"><x-input :name="'prices['.$id.'][retail_price]'" type="number" min="0" step="1" :value="$row['price']?->retail_price !== null ? (float) $row['price']->retail_price : null" :placeholder="(string) (float) $product->retail_price" prefix="TSh" class="mb-0" :aria-label="__('Retail price at :b', ['b' => $row['branch']->name])" :disabled="! auth()->user()->can('products.edit_price')" /></td>
                                        <td data-label="{{ __('Wholesale price') }}"><x-input :name="'prices['.$id.'][wholesale_price]'" type="number" min="0" step="1" :value="$row['price']?->wholesale_price !== null ? (float) $row['price']->wholesale_price : null" :placeholder="$product->wholesale_price ? (string) (float) $product->wholesale_price : '—'" prefix="TSh" class="mb-0" :aria-label="__('Wholesale price at :b', ['b' => $row['branch']->name])" :disabled="! auth()->user()->can('products.edit_price')" /></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @can('products.edit_price')
                            <div class="d-flex flex-wrap gap-2 align-items-end">
                                <x-input name="reason" :label="__('Reason (optional)')" class="mb-0 flex-grow-1" />
                                <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save branch prices') }}</button>
                            </div>
                        @endcan
                    </div>
                </form>
            </div>
        @endif

        @if (! $product->is_bundle && auth()->user()->canAny(['suppliers.view', 'purchases.view']))
            <div class="tab-pane fade" id="tab-suppliers">
                <div class="card">
                    @if ($productSuppliers->isEmpty())
                        <x-empty-state icon="bi-building" :title="__('No suppliers linked yet')" :message="__('Suppliers are linked automatically when you receive goods, or add one below.')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-stack align-middle mb-0">
                                <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Their code') }}</th>@if ($canCost)<th class="text-end">{{ __('Last cost') }}</th>@endif<th>{{ __('Last received') }}</th><th>{{ __('Lead time') }}</th><th></th></tr></thead>
                                <tbody>
                                @foreach ($productSuppliers as $ps)
                                    <tr>
                                        <td data-label="{{ __('Supplier') }}"><a href="{{ route('suppliers.show', $ps->supplier_id) }}">{{ $ps->supplier?->name }}</a>
                                            @if ($ps->is_preferred)<span class="badge text-bg-primary-soft"><i class="bi bi-star-fill"></i> {{ __('Preferred') }}</span>@endif</td>
                                        <td data-label="{{ __('Their code') }}" class="font-monospace">{{ $ps->supplier_sku ?: '—' }}</td>
                                        @if ($canCost)<td data-label="{{ __('Last cost') }}" class="text-end text-money">{{ $ps->last_cost !== null ? money($ps->last_cost) : '—' }}</td>@endif
                                        <td data-label="{{ __('Last received') }}">{{ $ps->last_received_at ? format_date($ps->last_received_at) : '—' }}</td>
                                        <td data-label="{{ __('Lead time') }}">{{ $ps->lead_time_days !== null ? trans_choice(':count day|:count days', $ps->lead_time_days) : '—' }}</td>
                                        <td class="text-end">
                                            @if ($supplierOptions->isNotEmpty())
                                                <form method="POST" action="{{ route('products.suppliers.destroy', [$product, $ps]) }}" data-confirm="{{ __('Remove :s from this product?', ['s' => $ps->supplier?->name]) }}">@csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-light text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if ($supplierOptions->isNotEmpty())
                        <form method="POST" action="{{ route('products.suppliers.store', $product) }}" class="card-body border-top">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4"><x-select name="supplier_id" :label="__('Supplier')" :options="$supplierOptions" :placeholder="__('Choose…')" searchable required class="mb-0" /></div>
                                <div class="col-md-3"><x-input name="supplier_sku" :label="__('Their code')" class="mb-0" /></div>
                                <div class="col-md-2"><x-input name="lead_time_days" type="number" min="0" :label="__('Lead time')" :suffix="__('days')" class="mb-0" /></div>
                                <div class="col-md-3 d-flex gap-2 align-items-center">
                                    <x-toggle name="is_preferred" :label="__('Preferred')" class="mb-0" />
                                    <button class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> {{ __('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
