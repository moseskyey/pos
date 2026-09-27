@php $editing = $announcement->exists; @endphp
<x-layouts.admin :title="$editing ? __('Edit announcement') : __('New announcement')" :breadcrumbs="[__('Announcements') => route('admin.announcements.index'), $editing ? $announcement->title : __('New')]">
    <x-page-header :title="$editing ? __('Edit announcement') : __('New announcement')" :subtitle="__('Keep it short: it appears as a banner above every page.')">
        @if ($editing)
            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" data-confirm="{{ __('Delete this announcement?') }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button></form>
        @endif
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" x-data="{ level: @js(old('level', $announcement->level)), title: @js(old('title', $announcement->title)), body: @js(old('body', $announcement->body)) }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Message')" icon="bi-megaphone">
                    <x-input name="title" :label="__('Title')" :value="$announcement->title" required maxlength="150" x-model="title" />
                    <x-textarea name="body" :label="__('Details')" :value="$announcement->body" rows="3" maxlength="1000" x-model="body" />
                    <div class="mb-0">
                        <span class="form-label d-block">{{ __('Style') }}</span>
                        <div class="btn-group" role="group" aria-label="{{ __('Style') }}">
                            @foreach (['info' => __('Info'), 'success' => __('Good news'), 'warning' => __('Warning'), 'danger' => __('Urgent')] as $value => $label)
                                <input type="radio" class="btn-check" name="level" id="level_{{ $value }}" value="{{ $value }}" x-model="level">
                                <label class="btn btn-outline-{{ $value }}" for="level_{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                </x-card>
                <div class="mt-4">
                    <div class="small text-body-secondary mb-1">{{ __('Preview') }}</div>
                    <div class="alert mb-0 small" :class="'alert-' + level" role="status"><i class="bi bi-megaphone"></i> <strong x-text="title || @js(__('Title'))"></strong> <span x-text="body"></span></div>
                </div>
            </div>
            <div class="col-lg-4">
                <x-card :title="__('Audience & timing')">
                    <x-select name="tenant_id" :label="__('Show to')" :options="$tenants->all()" :value="$announcement->tenant_id" :placeholder="__('All businesses')" searchable />
                    <x-input name="starts_at" type="datetime-local" :label="__('From')" :value="$announcement->starts_at?->format('Y-m-d\TH:i')" />
                    <x-input name="ends_at" type="datetime-local" :label="__('Until')" :value="$announcement->ends_at?->format('Y-m-d\TH:i')" :help="__('Blank: until you turn it off.')" />
                    <x-toggle name="is_active" :label="__('Active')" :checked="$announcement->is_active" class="mb-0" />
                </x-card>
            </div>
        </div>
        <x-form-actions :cancel="route('admin.announcements.index')" :label="$editing ? __('Save') : __('Post announcement')" />
    </form>
</x-layouts.admin>
