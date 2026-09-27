@php
    $editing = $promotion->exists;
    $qty = fn ($v) => $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');
    $days = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')];
@endphp
<x-layouts.app :title="$editing ? __('Edit promotion') : __('New promotion')" :breadcrumbs="[__('Promotions') => route('promotions.index'), $editing ? $promotion->name : __('New')]">
    <x-page-header :title="$editing ? $promotion->name : __('New promotion')">
        @if ($editing)
            <form method="POST" action="{{ route('promotions.toggle', $promotion) }}">@csrf
                <button class="btn {{ $promotion->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"><i class="bi bi-power"></i> {{ $promotion->is_active ? __('Switch off') : __('Switch on') }}</button>
            </form>
            <form method="POST" action="{{ route('promotions.destroy', $promotion) }}" data-confirm="{{ __('Delete this promotion? Past sales keep their discounts.') }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button>
            </form>
        @endif
    </x-page-header>

    <div x-data="{ type: @js(old('type', $promotion->type)), appliesTo: @js(old('applies_to', $promotion->applies_to)), happyHour: @js((bool) old('start_time', $promotion->start_time)) }">
    <form method="POST" action="{{ $editing ? route('promotions.update', $promotion) : route('promotions.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Offer')" icon="bi-megaphone">
                    <div class="row">
                        <div class="col-12"><x-input name="name" :label="__('Name')" :value="$promotion->name" required :placeholder="__('e.g. Weekend soda deal')" /></div>
                        <div class="col-12 mb-3">
                            <span class="form-label d-block">{{ __('Type of offer') }}</span>
                            <div class="row g-2" role="radiogroup">
                                @foreach (['percent' => ['bi-percent', __('% off')], 'amount' => ['bi-cash', __('Amount off each')], 'buy_get' => ['bi-gift', __('Buy X get Y free')], 'multi_price' => ['bi-tags', __('N for a price')]] as $value => [$icon, $label])
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check" name="type" id="type_{{ $value }}" value="{{ $value }}" x-model="type" @checked(old('type', $promotion->type) === $value)>
                                        <label class="btn btn-outline-primary w-100 py-3" for="type_{{ $value }}"><i class="bi {{ $icon }} d-block fs-5 mb-1" aria-hidden="true"></i><span class="small">{{ $label }}</span></label>
                                    </div>
                                @endforeach
                            </div>
                            @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" x-show="type === 'percent'" x-cloak><x-input name="value" type="number" step="0.01" min="0" max="100" :label="__('Discount')" :value="$promotion->type === 'percent' ? $qty($promotion->value) : null" suffix="%" x-bind:disabled="type !== 'percent'" /></div>
                        <div class="col-md-4" x-show="type === 'amount'" x-cloak><x-input name="value" type="number" step="1" min="0" :label="__('Off each item')" :value="$promotion->type === 'amount' ? $qty($promotion->value) : null" prefix="TSh" x-bind:disabled="type !== 'amount'" /></div>
                        <div class="col-md-4" x-show="type === 'buy_get' || type === 'multi_price'" x-cloak>
                            <x-input name="buy_qty" type="number" min="1" step="1" x-bind:disabled="type !== 'buy_get' && type !== 'multi_price'" :label="__('Buy')" :value="$qty($promotion->buy_qty)" :suffix="__('items')" />
                        </div>
                        <div class="col-md-4" x-show="type === 'buy_get'" x-cloak><x-input name="get_qty" type="number" min="1" step="1" x-bind:disabled="type !== 'buy_get'" :label="__('Get free')" :value="$qty($promotion->get_qty)" :suffix="__('items')" /></div>
                        <div class="col-md-4" x-show="type === 'multi_price'" x-cloak><x-input name="value" type="number" step="1" min="0" :label="__('For')" :value="$promotion->type === 'multi_price' ? $qty($promotion->value) : null" prefix="TSh" x-bind:disabled="type !== 'multi_price'" /></div>
                        <div class="col-md-4"><x-input name="min_qty" type="number" step="0.001" min="0" :label="__('Minimum quantity')" :value="$qty($promotion->min_qty)" :help="__('Optional. The item quantity needed before the offer applies.')" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Products')" icon="bi-box-seam" class="mt-4">
                    <div class="btn-group mb-3" role="radiogroup" aria-label="{{ __('Applies to') }}">
                        @foreach (['all' => __('Everything'), 'categories' => __('Categories'), 'products' => __('Chosen products')] as $value => $label)
                            <input type="radio" class="btn-check" name="applies_to" id="applies_{{ $value }}" value="{{ $value }}" x-model="appliesTo">
                            <label class="btn btn-outline-secondary" for="applies_{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>
                    <div x-show="appliesTo === 'categories'" x-cloak>
                        <x-select name="category_ids[]" :label="__('Categories')" :options="$categories" :value="old('category_ids', $promotion->targets->pluck('category_id')->filter()->all())" multiple searchable
                                  :help="__('Choosing a main category includes its sub-categories.')" />
                    </div>
                    <div x-show="appliesTo === 'products'" x-cloak>
                        <x-select name="product_ids[]" :label="__('Products')" :options="$products" :value="old('product_ids', $promotion->targets->pluck('product_id')->filter()->all())" multiple searchable
                                  :help="__('Choosing a product with variants includes every variant.')" />
                    </div>
                    <p class="small text-body-secondary mb-0" x-show="appliesTo === 'all'">{{ __('The offer applies to every product.') }}</p>
                </x-card>
            </div>

            <div class="col-lg-4">
                <x-card :title="__('When')" icon="bi-calendar-event">
                    <div class="row">
                        <div class="col-6"><x-input name="starts_on" type="date" :label="__('From')" :value="$promotion->starts_on?->toDateString()" /></div>
                        <div class="col-6"><x-input name="ends_on" type="date" :label="__('Until')" :value="$promotion->ends_on?->toDateString()" /></div>
                    </div>
                    <span class="form-label d-block">{{ __('Days') }}</span>
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        @foreach ($days as $n => $label)
                            <input type="checkbox" class="btn-check" name="days_of_week[]" id="day_{{ $n }}" value="{{ $n }}" @checked(in_array($n, array_map('intval', old('days_of_week', $promotion->days_of_week ?? []))))>
                            <label class="btn btn-sm btn-outline-secondary" for="day_{{ $n }}">{{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="form-text mb-3">{{ __('None selected = every day.') }}</div>
                    <x-toggle name="happy_hour" :label="__('Only at certain hours (happy hour)')" x-model="happyHour" :checked="(bool) $promotion->start_time" />
                    <div class="row" x-show="happyHour" x-cloak>
                        <div class="col-6"><x-input name="start_time" type="time" :label="__('From')" :value="$promotion->start_time ? substr($promotion->start_time, 0, 5) : null" x-bind:disabled="! happyHour" /></div>
                        <div class="col-6"><x-input name="end_time" type="time" :label="__('To')" :value="$promotion->end_time ? substr($promotion->end_time, 0, 5) : null" x-bind:disabled="! happyHour" /></div>
                    </div>
                </x-card>
                <x-card :title="__('Where & priority')" icon="bi-shop" class="mt-4">
                    @if (count($branches) > 1)
                        <x-select name="branch_ids[]" :label="__('Branches')" :options="$branches" :value="old('branch_ids', $promotion->branch_ids ?? [])" multiple :help="__('None selected = every branch.')" />
                    @endif
                    <x-input name="priority" type="number" min="0" max="100" :label="__('Priority')" :value="$promotion->priority" :help="__('Only used to break a tie between offers that save the same amount.')" />
                    <x-toggle name="is_active" :label="__('Switched on')" :checked="$promotion->is_active" class="mb-0" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('promotions.index')" :save-new="! $editing" />
    </form>
    </div>
</x-layouts.app>
