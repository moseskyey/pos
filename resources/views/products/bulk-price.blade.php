<x-layouts.app :title="__('Bulk price update')" :breadcrumbs="[__('Products') => route('products.index'), __('Bulk price update')]">
    <x-page-header :title="__('Bulk price update')" :subtitle="__('Change prices for a whole category or brand at once. Every change is logged.')" />

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="GET" action="{{ route('products.bulk-price') }}" id="previewForm">
                <x-card :title="__('1. Choose products & change')" icon="bi-funnel">
                    <div class="row">
                        <div class="col-md-6"><x-select name="category_id" :label="__('Category')" :options="$categories" :value="$filters['category_id'] ?? null" :placeholder="__('All categories')" searchable /></div>
                        <div class="col-md-6"><x-select name="brand_id" :label="__('Brand')" :options="$brands" :value="$filters['brand_id'] ?? null" :placeholder="__('All brands')" searchable /></div>
                        <div class="col-md-6"><x-select name="field" :label="__('Price to change')" :options="['retail_price' => __('Retail price'), 'wholesale_price' => __('Wholesale price'), 'both' => __('Retail & wholesale')]" :value="$filters['field'] ?? 'retail_price'" /></div>
                        <div class="col-md-6"><x-select name="mode" :label="__('Change type')" :options="['percent' => __('Increase/decrease by %'), 'fixed' => __('Add/subtract amount (TSh)'), 'set' => __('Set exact price (TSh)')]" :value="$filters['mode'] ?? 'percent'" /></div>
                        <div class="col-md-6"><x-input name="value" type="number" step="0.01" :label="__('Value')" :value="$filters['value'] ?? null" required :help="__('Use a negative number to decrease, e.g. -5')" /></div>
                        <div class="col-md-6"><x-select name="rounding" :label="__('Round result to')" :options="['0' => __('No rounding'), '50' => 'TSh 50', '100' => 'TSh 100']" :value="$filters['rounding'] ?? '0'" /></div>
                    </div>
                    <button class="btn btn-outline-primary"><i class="bi bi-eye"></i> {{ __('Preview') }}</button>
                </x-card>
            </form>
        </div>

        <div class="col-lg-5">
            <x-card :title="__('2. Preview & apply')" icon="bi-check2-square" :flush="true">
                @if ($sample->isEmpty())
                    <x-empty-state icon="bi-eye" :title="__('No preview yet')" :message="__('Choose filters and a value, then click Preview.')" />
                @else
                    <div class="px-3 pt-3 small text-body-secondary">{{ trans_choice(':count product will change|:count products will change', $count) }} · {{ __('showing first 10') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Now') }}</th><th class="text-end">{{ __('New') }}</th></tr></thead>
                            <tbody>
                            @foreach ($sample as $s)
                                <tr>
                                    <td class="small">{{ $s['name'] }}</td>
                                    <td class="text-end text-money text-body-secondary small">{{ $s['old'] !== null ? money($s['old']) : '—' }}</td>
                                    <td class="text-end text-money fw-semibold small">{{ $s['new'] !== null ? money($s['new']) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="{{ route('products.bulk-price.store') }}" class="p-3 border-top" data-confirm="{{ __('Update prices for :n products?', ['n' => $count]) }}">
                        @csrf
                        @foreach (['category_id', 'brand_id', 'field', 'mode', 'value', 'rounding'] as $k)
                            <input type="hidden" name="{{ $k }}" value="{{ $filters[$k] ?? '' }}">
                        @endforeach
                        @if (empty($filters['category_id']) && empty($filters['brand_id']))
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="confirm_all" value="1" id="confirm_all" required>
                                <label class="form-check-label small text-danger" for="confirm_all">{{ __('Yes, update ALL products') }}</label>
                            </div>
                        @endif
                        <x-input name="reason" :label="__('Reason')" :placeholder="__('e.g. Supplier price increase')" />
                        <button class="btn btn-primary w-100"><i class="bi bi-check2"></i> {{ __('Apply to :n products', ['n' => $count]) }}</button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
