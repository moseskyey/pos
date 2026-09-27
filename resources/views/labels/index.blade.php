<x-layouts.app :title="__('Barcode labels')" :breadcrumbs="[__('Products') => route('products.index'), __('Barcode labels')]">
    <x-page-header :title="__('Barcode labels')" :subtitle="__('Choose products and how many labels to print for each.')" />

    <form method="POST" action="{{ route('labels.print') }}" target="_blank"
          x-data="{ items: @js($selected->map(fn ($p) => ['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'quantity' => 1])->values()), products: @js($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->values()), pick: '' ,
                   add() { const p = this.products.find(p => String(p.id) === String(this.pick)); if (p && !this.items.find(i => i.product_id === p.id)) this.items.push({ product_id: p.id, name: p.name, sku: p.sku, quantity: 1 }); this.pick = ''; },
                   get total() { return this.items.reduce((s, i) => s + Number(i.quantity || 0), 0) } }">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Products')" icon="bi-box-seam">
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select" x-model="pick" @change="add()" aria-label="{{ __('Add product') }}">
                            <option value="">{{ __('Add a product…') }}</option>
                            <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name + ' (' + p.sku + ')'"></option></template>
                        </select>
                    </div>
                    <template x-if="items.length === 0">
                        <x-empty-state icon="bi-upc" :title="__('No products selected')" :message="__('Pick products above, or select them from the products list.')" />
                    </template>
                    <div class="table-responsive" x-show="items.length">
                        <table class="table align-middle">
                            <thead><tr><th>{{ __('Product') }}</th><th style="width:140px">{{ __('Labels') }}</th><th></th></tr></thead>
                            <tbody>
                            <template x-for="(item, i) in items" :key="item.product_id">
                                <tr>
                                    <td><div class="fw-semibold" x-text="item.name"></div><div class="small text-body-secondary font-monospace" x-text="item.sku"></div>
                                        <input type="hidden" :name="`items[${i}][product_id]`" :value="item.product_id"></td>
                                    <td><input type="number" min="1" max="500" class="form-control" :name="`items[${i}][quantity]`" x-model.number="item.quantity"></td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-light text-danger" @click="items.splice(i, 1)"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Label format')" icon="bi-printer">
                    <x-select name="size" :label="__('Size')" :options="collect($sizes)->map(fn ($s) => $s['label'])->all()" value="a4-30" />
                    <x-toggle name="show_name" :label="__('Show product name')" :checked="true" />
                    <x-toggle name="show_price" :label="__('Show price')" :checked="true" />
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="text-body-secondary">{{ __('Total labels') }}: <strong x-text="total"></strong></span>
                        <button class="btn btn-primary" :disabled="!items.length"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
