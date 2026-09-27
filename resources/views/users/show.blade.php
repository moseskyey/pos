<x-layouts.app :title="$user->name" :breadcrumbs="[__('Users') => route('users.index'), $user->name]">
    <div class="page-header">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <x-avatar :user="$user" size="lg" />
            <div class="min-w-0">
                <h2 class="text-truncate">{{ $user->name }}</h2>
                <div class="d-flex flex-wrap gap-2 mt-1 align-items-center">
                    <span class="badge rounded-pill text-bg-primary-soft">{{ $user->roleLabel() }}</span>
                    <x-status-badge :status="$user->is_active ? 'active' : 'inactive'" />
                    @if ($user->hasTwoFactorEnabled())<span class="badge rounded-pill text-bg-success-soft"><i class="bi bi-shield-check"></i> 2FA</span>@endif
                </div>
            </div>
        </div>
        <div class="page-actions">
            @can('impersonate', $user)
                <form method="POST" action="{{ route('users.impersonate', $user) }}" data-confirm="{{ __('Sign in as :name? This is logged.', ['name' => $user->name]) }}">@csrf
                    <button class="btn btn-outline-warning"><i class="bi bi-incognito"></i> {{ __('Login as') }}</button>
                </form>
            @endcan
            @can('update', $user)
                <a href="{{ route('users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
            @endcan
            @can('delete', $user)
                <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="{{ __('Delete this user? They will no longer be able to sign in.') }}">@csrf @method('DELETE')
                    <button class="btn btn-soft-danger"><i class="bi bi-trash"></i></button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <x-card :title="__('Details')">
                <dl class="info-list mb-0">
                    <dt>{{ __('Email') }}</dt><dd>{{ $user->email }}</dd>
                    <dt>{{ __('Phone') }}</dt><dd>{{ $user->phone ? \App\Support\PhoneNumber::display($user->phone) : '—' }}</dd>
                    <dt>{{ __('Branches') }}</dt>
                    <dd>@forelse ($user->branches as $b)<span class="badge text-bg-secondary-soft me-1">{{ $b->name }}</span>@empty — @endforelse</dd>
                    <dt>{{ __('Default branch') }}</dt><dd>{{ $user->defaultBranch?->name ?? '—' }}</dd>
                    <dt>{{ __('Manager PIN') }}</dt><dd>{{ $user->hasPin() ? __('Set') : __('Not set') }}</dd>
                    <dt>{{ __('Last login') }}</dt><dd>{{ format_date($user->last_login_at, true) }}</dd>
                    <dt>{{ __('Member since') }}</dt><dd class="mb-0">{{ format_date($user->created_at) }}</dd>
                </dl>
            </x-card>
        </div>
        <div class="col-lg-8">
            <x-card :title="__('Recent activity')" :flush="true">
                @if ($activities->isEmpty())
                    <x-empty-state icon="bi-clock-history" :title="__('No activity yet')" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($activities as $a)
                            <li class="list-group-item d-flex gap-3 py-3">
                                <div class="rounded-circle bg-primary-soft text-primary d-grid flex-shrink-0" style="width:34px;height:34px;place-items:center"><i class="bi bi-activity"></i></div>
                                <div class="min-w-0">
                                    <div class="fw-medium">{{ $a->description }}</div>
                                    <div class="small text-body-secondary">{{ $a->log_name }} · {{ $a->created_at->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
