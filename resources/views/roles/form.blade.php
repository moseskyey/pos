@php $editing = $role->exists; @endphp
<x-layouts.app :title="$editing ? __('Edit role') : __('New role')" :breadcrumbs="[__('Roles') => route('roles.index'), $editing ? $role->name : __('New')]">
    <x-page-header :title="$editing ? config('dukapos.roles.'.$role->name.'.label', \Illuminate\Support\Str::headline($role->name)) : __('New role')" :subtitle="__('Tick the permissions this role should have.')" />

    <form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" x-data="dirtyForm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <x-card :title="__('Role name')">
                    <x-input name="name" :label="__('Name')" :value="$role->name" required :help="__('Lowercase, e.g. senior_cashier')" class="mb-0" />
                </x-card>
                <div class="row g-3 mt-1 permission-grid">
                    @foreach (config('dukapos.permissions') as $group => $permissions)
                        <div class="col-md-6" x-data>
                            <div class="card h-100">
                                <div class="card-header">
                                    <span>{{ __($group) }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                            @click="const boxes = $el.closest('.card').querySelectorAll('input[type=checkbox]'); const all = [...boxes].every(b => b.checked); boxes.forEach(b => b.checked = !all); $el.closest('form').dispatchEvent(new Event('change'))">
                                        {{ __('Toggle all') }}
                                    </button>
                                </div>
                                <div class="card-body">
                                    @foreach ($permissions as $name => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $name }}" id="perm_{{ $name }}" @checked(in_array($name, old('permissions', $assigned), true))>
                                            <label class="form-check-label" for="perm_{{ $name }}">{{ __($label) }} <code class="small text-body-secondary ms-1">{{ $name }}</code></label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card bg-primary-soft border-0 position-sticky" style="top: 80px">
                    <div class="card-body small">
                        <div class="fw-semibold mb-2"><i class="bi bi-info-circle text-primary"></i> {{ __('How permissions work') }}</div>
                        <ul class="ps-3 mb-0">
                            <li>{{ __('The Owner role always has every permission.') }}</li>
                            <li>{{ __('Cost price and profit are hidden without “View cost, profit & margins”.') }}</li>
                            <li>{{ __('Users without an override permission can still perform it with a manager PIN.') }}</li>
                            <li>{{ __('“View all branches” shows data from every branch.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <x-form-actions :cancel="route('roles.index')" />
    </form>
</x-layouts.app>
