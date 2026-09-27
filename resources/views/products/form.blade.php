@php
    $editing = $product->exists;
    $canPrice = auth()->user()->can('products.edit_price');
    $canCost = auth()->user()->can('products.view_cost');
    $initialBarcodes = old('barcodes', $product->relationLoaded('barcodes') ? $product->barcodes->whereNull('product_unit_id')->pluck('barcode')->values()->all() : []);
    $initialUnits = old('units', $product->relationLoaded('units') ? $product->units->map(fn ($u) => [
        'unit_id' => $u->unit_id, 'factor' => (float) $u->factor, 'retail_price' => (float) $u->retail_price,
        'wholesale_price' => $u->wholesale_price !== null ? (float) $u->wholesale_price : null,
        'barcode' => $u->barcodes->first()?->barcode,
    ])->values()->all() : []);
    $initialVariants = old('variants', $product->relationLoaded('variants') ? $product->variants->map(fn ($v) => [
        'id' => $v->id, 'attributes' => (object) ($v->variant_attributes ?? []), 'sku' => $v->sku,
        'barcode' => $v->barcodes->first()?->barcode, 'retail_price' => (float) $v->retail_price, 'is_active' => $v->is_active,
    ] + ($canCost ? ['cost_price' => (float) $v->cost_price] : []))->values()->all() : []);
    $attributeNames = collect($initialVariants)->flatMap(fn ($v) => array_keys((array) $v['attributes']))->unique()->values()->all() ?: ['Size', 'Colour'];
@endphp
<x-layouts.app :title="$editing ? __('Edit product') : __('New product')" :breadcrumbs="[__('Products') => route('products.index'), $editing ? $product->name : __('New')]">
    <x-page-header :title="$editing ? $product->name : __('New product')" :subtitle="$editing ? __('SKU').': '.$product->sku : __('Fill in the details below. Only name, unit and price are required.')" />

    <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data"
          x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4" x-data="{
              cost: @js($canCost ? (float) old('cost_price', $product->cost_price ?? 0) : 0),
              price: @js((float) old('retail_price', $product->retail_price ?? 0)),
              baseUnit: @js((string) old('unit_id', $product->unit_id)),
              hasVariants: @js((bool) old('has_variants', $product->has_variants)),
              barcodes: @js(array_values($initialBarcodes)),
              units: @js(array_values($initialUnits)),
              variants: @js(array_values($initialVariants)),
              attrs: @js($attributeNames),
              conversions: @js($conversions),
              get margin() { return this.price > 0 ? ((this.price - this.cost) / this.price * 100) : 0 },
              ean() { const body = '2' + String(Date.now()).slice(-11); let s = 0; for (let i = 0; i < 12; i++) s += +body[i] * (i % 2 ? 3 : 1); return body + ((10 - s % 10) % 10); },
              suggestFactor(row) { const c = this.conversions.find(c => String(c.from_unit_id) === String(row.unit_id) && String(c.to_unit_id) === String(this.baseUnit)); if (c && !row.factor) { row.factor = +c.factor; if (!row.retail_price && this.price) row.retail_price = +(c.factor * this.price).toFixed(0); } },
              addVariant() { const a = {}; this.attrs.forEach(n => a[n] = ''); this.variants.push({ id: null, attributes: a, sku: '', barcode: '', retail_price: this.price, @if ($canCost) cost_price: this.cost, @endif is_active: true }); },
          }">
            <div class="col-lg-8">
                <x-card :title="__('Basic information')" icon="bi-info-circle">
                    <div class="row">
                        <div class="col-md-8"><x-input name="name" :label="__('Product name')" :value="$product->name" required placeholder="Azam Maji 500ml" /></div>
                        <div class="col-md-4"><x-input name="sku" :label="__('SKU')" :value="$editing ? $product->sku : null" :placeholder="__('Auto-generated')" /></div>
                        <div class="col-md-4"><x-select name="category_id" :label="__('Category')" :options="$categories" :value="$product->category_id" :placeholder="__('Uncategorised')" searchable /></div>
                        <div class="col-md-4"><x-select name="brand_id" :label="__('Brand')" :options="$brands" :value="$product->brand_id" :placeholder="__('No brand')" searchable /></div>
                        <div class="col-md-4"><x-select name="unit_id" :label="__('Base unit')" :options="$units" :value="$product->unit_id" required x-model="baseUnit" /></div>
                        <div class="col-12"><x-textarea name="description" :label="__('Description')" :value="$product->description" rows="2" class="mb-0" /></div>
                    </div>
                </x-card>

                @if ($canPrice || $canCost)
                    <x-card :title="__('Pricing & tax')" icon="bi-tag" class="mt-4">
                        <div class="row">
                            @if ($canCost)
                                <div class="col-md-4"><x-input name="cost_price" type="number" step="0.01" min="0" :label="__('Cost price')" :value="$product->cost_price" prefix="TSh" x-model.number="cost" /></div>
                            @endif
                            @if ($canPrice)
                                <div class="col-md-4"><x-input name="retail_price" type="number" step="0.01" min="0" :label="__('Retail price')" :value="$product->retail_price" prefix="TSh" required x-model.number="price" /></div>
                                <div class="col-md-4"><x-select name="tax_type" :label="__('VAT')" :options="$taxTypes" :value="$product->tax_type?->value" /></div>
                                <div class="col-md-4"><x-input name="wholesale_price" type="number" step="0.01" min="0" :label="__('Wholesale price')" :value="$product->wholesale_price" prefix="TSh" /></div>
                                <div class="col-md-4"><x-input name="wholesale_min_qty" type="number" step="0.001" min="0" :label="__('Wholesale from qty')" :value="$product->wholesale_min_qty !== null ? (float) $product->wholesale_min_qty : null" :help="__('Auto-applies at this quantity.')" /></div>
                                @if ($editing)
                                    <div class="col-md-4"><x-input name="price_reason" :label="__('Reason for price change')" :placeholder="__('Optional')" /></div>
                                @endif
                            @else
                                <input type="hidden" name="tax_type" value="{{ $product->tax_type?->value ?? 'standard' }}">
                            @endif
                        </div>
                    </x-card>
                @else
                    <input type="hidden" name="tax_type" value="{{ $product->tax_type?->value ?? 'standard' }}">
                @endif

                <x-card :title="__('Barcodes')" icon="bi-upc" class="mt-4" :subtitle="__('Scan or type. A product can have several barcodes.')">
                    <template x-for="(code, i) in barcodes" :key="i">
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" class="form-control font-monospace" :name="`barcodes[${i}]`" x-model="barcodes[i]" @keydown.enter.prevent aria-label="{{ __('Barcode') }}">
                            <button type="button" class="btn btn-outline-danger" @click="barcodes.splice(i, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </template>
                    @error('barcodes.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-soft-primary" @click="barcodes.push('')"><i class="bi bi-plus-lg"></i> {{ __('Add barcode') }}</button>
                        <button type="button" class="btn btn-sm btn-light" @click="barcodes.push(ean())"><i class="bi bi-magic"></i> {{ __('Generate EAN-13') }}</button>
                    </div>
                </x-card>

                @if ($canPrice)
                    <x-card :title="__('Other selling units')" icon="bi-boxes" class="mt-4" :subtitle="__('e.g. sell by carton (24 pcs) or dozen with their own prices.')">
                        <template x-if="units.length">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>{{ __('Unit') }}</th><th>{{ __('Contains') }}</th><th>{{ __('Retail') }}</th><th>{{ __('Wholesale') }}</th><th>{{ __('Barcode') }}</th><th></th></tr></thead>
                                    <tbody>
                                    <template x-for="(row, i) in units" :key="i">
                                        <tr>
                                            <td style="min-width:140px">
                                                <select class="form-select form-select-sm" :name="`units[${i}][unit_id]`" x-model="row.unit_id" @change="suggestFactor(row)" required>
                                                    <option value="">—</option>
                                                    @foreach ($units as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                                </select>
                                            </td>
                                            <td style="min-width:110px"><input type="number" step="0.0001" min="0" class="form-control form-control-sm" :name="`units[${i}][factor]`" x-model="row.factor" required :aria-label="'{{ __('Base units per unit') }}'"></td>
                                            <td style="min-width:110px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" :name="`units[${i}][retail_price]`" x-model="row.retail_price"></td>
                                            <td style="min-width:110px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" :name="`units[${i}][wholesale_price]`" x-model="row.wholesale_price"></td>
                                            <td style="min-width:140px"><input type="text" class="form-control form-control-sm font-monospace" :name="`units[${i}][barcode]`" x-model="row.barcode" @keydown.enter.prevent></td>
                                            <td><button type="button" class="btn btn-sm btn-light text-danger" @click="units.splice(i, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button></td>
                                        </tr>
                                    </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        @error('units.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @foreach ($errors->getMessages() as $key => $messages)
                            @if (str_starts_with($key, 'units.'))<div class="text-danger small">{{ $messages[0] }}</div>@endif
                        @endforeach
                        <button type="button" class="btn btn-sm btn-soft-primary" @click="units.push({ unit_id: '', factor: '', retail_price: '', wholesale_price: '', barcode: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add unit') }}</button>
                    </x-card>
                @endif

                <x-card :title="__('Variants')" icon="bi-grid-3x3" class="mt-4" :subtitle="__('For boutiques: sizes, colours… each variant has its own SKU, stock and price.')">
                    <input type="hidden" name="has_variants" value="0">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="has_variants" name="has_variants" value="1" x-model="hasVariants">
                        <label class="form-check-label" for="has_variants">{{ __('This product has variants') }}</label>
                    </div>
                    <div x-show="hasVariants" x-cloak>
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <span class="small text-body-secondary">{{ __('Attributes:') }}</span>
                            <template x-for="(a, i) in attrs" :key="i">
                                <span class="badge rounded-pill text-bg-primary-soft d-inline-flex align-items-center gap-1">
                                    <span x-text="a"></span>
                                    <button type="button" class="btn-close" style="font-size:.5rem" @click="attrs.splice(i, 1)" x-show="attrs.length > 1"></button>
                                </span>
                            </template>
                            <input type="text" class="form-control form-control-sm w-auto" placeholder="{{ __('Add attribute') }}" @keydown.enter.prevent="if ($el.value.trim()) { attrs.push($el.value.trim()); $el.value = '' }">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr>
                                    <template x-for="a in attrs"><th x-text="a"></th></template>
                                    <th>{{ __('SKU') }}</th><th>{{ __('Barcode') }}</th>
                                    @if ($canPrice)<th>{{ __('Price') }}</th>@endif
                                    @if ($canCost)<th>{{ __('Cost') }}</th>@endif
                                    <th>{{ __('Active') }}</th><th></th>
                                </tr></thead>
                                <tbody>
                                <template x-for="(v, i) in variants" :key="i">
                                    <tr>
                                        <template x-for="a in attrs"><td style="min-width:90px"><input type="text" class="form-control form-control-sm" :name="`variants[${i}][attributes][${a}]`" x-model="v.attributes[a]"></td></template>
                                        <td style="min-width:120px">
                                            <input type="hidden" :name="`variants[${i}][id]`" :value="v.id ?? ''">
                                            <input type="text" class="form-control form-control-sm font-monospace" :name="`variants[${i}][sku]`" x-model="v.sku" placeholder="{{ __('Auto') }}">
                                        </td>
                                        <td style="min-width:130px"><input type="text" class="form-control form-control-sm font-monospace" :name="`variants[${i}][barcode]`" x-model="v.barcode" @keydown.enter.prevent></td>
                                        @if ($canPrice)<td style="min-width:100px"><input type="number" step="0.01" class="form-control form-control-sm" :name="`variants[${i}][retail_price]`" x-model="v.retail_price"></td>@endif
                                        @if ($canCost)<td style="min-width:100px"><input type="number" step="0.01" class="form-control form-control-sm" :name="`variants[${i}][cost_price]`" x-model="v.cost_price"></td>@endif
                                        <td><input type="hidden" :name="`variants[${i}][is_active]`" :value="v.is_active ? 1 : 0"><input type="checkbox" class="form-check-input" x-model="v.is_active"></td>
                                        <td><button type="button" class="btn btn-sm btn-light text-danger" @click="variants.splice(i, 1)"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                        @foreach ($errors->getMessages() as $key => $messages)
                            @if (str_starts_with($key, 'variants'))<div class="text-danger small">{{ $messages[0] }}</div>@endif
                        @endforeach
                        <button type="button" class="btn btn-sm btn-soft-primary" @click="addVariant()"><i class="bi bi-plus-lg"></i> {{ __('Add variant') }}</button>
                    </div>
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card :title="__('Image')">
                    <x-file-upload name="image" :current="$product->imageUrl()" :help="__('PNG or JPG, up to 4MB')" class="mb-0" />
                </x-card>

                @if ($canPrice && $canCost)
                    <div class="card mt-4 bg-primary-soft border-0">
                        <div class="card-body">
                            <div class="small text-body-secondary">{{ __('Gross margin') }}</div>
                            <div class="fs-3 fw-bold" :class="margin < 0 ? 'text-danger' : 'text-primary'" x-text="margin.toFixed(1) + '%'"></div>
                            <div class="small text-body-secondary">{{ __('Profit per unit') }}: <strong x-text="'TSh ' + Math.round(price - cost).toLocaleString()"></strong></div>
                        </div>
                    </div>
                @endif

                <x-card :title="__('Inventory')" class="mt-4">
                    <x-toggle name="track_stock" :label="__('Track stock')" :checked="$product->track_stock" :help="__('Turn off for services.')" />
                    <x-toggle name="track_batches" :label="__('Track batches & expiry')" :checked="$product->track_batches" :help="__('Pharmacy / food items. FEFO on sale.')" />
                    <x-toggle name="is_weighted" :label="__('Sold by weight (scale barcode)')" :checked="$product->is_weighted" />
                    <x-input name="reorder_level" type="number" step="0.001" min="0" :label="__('Reorder level')" :value="$product->reorder_level !== null ? (float) $product->reorder_level : 0" :help="__('Low-stock alert at or below this quantity.')" class="mb-0" />
                </x-card>

                <x-card :title="__('Status')" class="mt-4">
                    <x-toggle name="is_active" :label="__('Active (available for sale)')" :checked="$product->is_active" class="mb-0" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="$editing ? route('products.show', $product) : route('products.index')" :save-new="! $editing" />
    </form>
</x-layouts.app>
