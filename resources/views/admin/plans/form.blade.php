@php $editing = $plan->exists; @endphp
<x-layouts.admin :title="$editing ? __('Edit plan') : __('New plan')" :breadcrumbs="[__('Plans') => route('admin.plans.index'), $editing ? $plan->name : __('New')]">
    <x-page-header :title="$editing ? __('Edit :name', ['name' => $plan->name]) : __('New plan')" :subtitle="__('Leave a limit empty for unlimited.')">
        @if ($editing)
            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" data-confirm="{{ __('Delete this plan? If businesses use it, it is deactivated instead.') }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button></form>
        @endif
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Plan')" icon="bi-stars">
                    <div class="row">
                        <div class="col-md-8"><x-input name="name" :label="__('Name')" :value="$plan->name" required maxlength="80" placeholder="Duka Standard" /></div>
                        <div class="col-md-4"><x-input name="slug" :label="__('Code')" :value="$plan->slug" maxlength="80" :help="__('Blank: made from the name.')" /></div>
                        <div class="col-12"><x-input name="description" :label="__('Short description')" :value="$plan->description" maxlength="255" placeholder="{{ __('For one shop with up to 3 tills') }}" /></div>
                        <div class="col-md-6"><x-input name="price" type="number" min="0" step="0.01" :label="__('Price')" :value="$plan->price" required prefix="TSh" /></div>
                        <div class="col-md-6"><x-select name="interval_months" :label="__('Billed every')" :options="[1 => __('1 month'), 3 => __('3 months'), 6 => __('6 months'), 12 => __('12 months')]" :value="$plan->interval_months" required /></div>
                    </div>
                </x-card>
                <x-card :title="__('Limits')" icon="bi-sliders" class="mt-4" :subtitle="__('What a business on this plan can have at the same time.')">
                    <div class="row">
                        <div class="col-md-4"><x-input name="max_branches" type="number" min="1" :label="__('Branches')" :value="$plan->max_branches" placeholder="∞" class="mb-0" /></div>
                        <div class="col-md-4"><x-input name="max_users" type="number" min="1" :label="__('Active users')" :value="$plan->max_users" placeholder="∞" class="mb-0" /></div>
                        <div class="col-md-4"><x-input name="max_products" type="number" min="1" :label="__('Products')" :value="$plan->max_products" placeholder="∞" class="mb-0" /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Visibility')">
                    <x-toggle name="is_active" :label="__('Available for new subscriptions')" :checked="$plan->is_active" :help="__('Inactive plans are hidden from sign-up and billing; businesses already on them keep them.')" />
                    <x-input name="sort_order" type="number" min="0" :label="__('Display order')" :value="$plan->sort_order" class="mb-0" />
                </x-card>
                @if ($editing)
                    <div class="card mt-4 bg-primary-soft border-0"><div class="card-body small">
                        <i class="bi bi-info-circle text-primary"></i> {{ trans_choice(':count business uses this plan.|:count businesses use this plan.', $plan->tenants_count) }}
                    </div></div>
                @endif
            </div>
        </div>
        <x-form-actions :cancel="route('admin.plans.index')" :save-new="! $editing" />
    </form>
</x-layouts.admin>
