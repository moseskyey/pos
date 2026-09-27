<x-layouts.app :title="__('Roles')" :breadcrumbs="[__('Settings'), __('Roles')]">
    <x-page-header :title="__('Roles & permissions')" :subtitle="__('Control what each role can see and do.')">
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add role') }}</a>
    </x-page-header>

    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 hover-lift">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-primary-soft text-primary rounded-3 d-grid" style="width:44px;height:44px;place-items:center;font-size:1.2rem">
                                <i class="bi {{ $role->name === 'owner' ? 'bi-stars' : 'bi-shield-lock' }}"></i>
                            </div>
                            @if (array_key_exists($role->name, config('dukapos.roles')))
                                <span class="badge rounded-pill text-bg-secondary-soft">{{ __('Built-in') }}</span>
                            @endif
                        </div>
                        <h5 class="fw-semibold mb-1">{{ config("dukapos.roles.{$role->name}.label", \Illuminate\Support\Str::headline($role->name)) }}</h5>
                        <div class="small text-body-secondary mb-3">
                            {{ $role->name === 'owner' ? __('All permissions') : trans_choice(':count permission|:count permissions', $role->permissions_count) }}
                            · {{ trans_choice(':count user|:count users', $role->users_count) }}
                        </div>
                        <div class="d-flex gap-2">
                            @if ($role->name !== 'owner')
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-soft-primary"><i class="bi bi-pencil"></i> {{ __('Edit permissions') }}</a>
                            @endif
                            @unless (array_key_exists($role->name, config('dukapos.roles')))
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm="{{ __('Delete this role?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-soft-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
